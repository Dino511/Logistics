<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientFleetCapacityException;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Inventory\Location;
use App\Models\Inventory\Stock;
use App\Models\Inventory\Supplier;
use App\Models\Shipment;
use App\Models\ShipmentPickup;
use App\Models\Vehicle;
use App\Models\VehicleLocationPing;
use App\Services\Fleet\FleetAllocationService;
use App\Services\Geocoder;
use App\Services\ShipmentAlerts;
use App\Services\StockReservations;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ShipmentController extends Controller
{
    /** The printable list stops at this many rows. */
    private const PRINT_LIMIT = 500;

    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $shipments = $this->listQuery($status, $search)
            ->simplePaginate(15)
            ->withQueryString();

        // Field Personnel also get their own open deliveries at the top of the page.
        $user = $request->user();
        $myDeliveries = null;
        if ($user->hasRole('field_personnel')) {
            $myDeliveries = $user->driver
                ? Shipment::assignedToDriver($user->driver->id)
                    ->whereIn('status', StockReservations::HOLDING_STATUSES)
                    ->orderBy('scheduled_delivery_at')
                    ->get()
                : collect();
        }

        return view('shipments.index', [
            'shipments' => $shipments,
            'myDeliveries' => $myDeliveries,
            'isLinkedDriver' => (bool) $user->driver,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function create(StockReservations $reservations)
    {
        $stockOptions = [];
        $inventoryDown = false;
        try {
            // Only stock that is actually free: on hand in Inventory, minus what open
            // shipments have already reserved, for active products.
            $reserved = $reservations->reserved();
            $stockOptions = Stock::with(['product', 'location'])
                ->where('quantity', '>', 0)
                ->get()
                ->filter(fn ($s) => $s->product && $s->product->is_active && $s->location)
                ->map(fn ($s) => [
                    'key' => "{$s->product_id}:{$s->location_id}",
                    'label' => "{$s->product->name} ({$s->product->sku}) – {$s->location->name}",
                    'product' => "{$s->product->name} ({$s->product->sku})",
                    'location_id' => (int) $s->location_id,
                    'available' => $reservations->available((int) $s->quantity, $reserved["{$s->product_id}:{$s->location_id}"] ?? 0),
                ])
                ->filter(fn ($o) => $o['available'] > 0)
                ->sortBy('label')->values()->all();
        } catch (\Throwable $e) {
            report($e);
            $inventoryDown = true;
        }

        // Warehouses/stores and suppliers from Inventory, suggested in the
        // origin and destination name fields. Users can still type any name.
        $placeOptions = [];
        if (! $inventoryDown) {
            try {
                $locations = Location::with('company')->active()->orderBy('name')->get()
                    ->map(fn ($l) => [
                        'name' => $l->name,
                        'address' => (string) $l->address,
                        'type' => 'Location',
                        'location_id' => (int) $l->id,
                        'sub' => $l->company->name ?? null,
                        'lat' => $l->latitude,
                        'lng' => $l->longitude,
                    ]);
                $suppliers = Supplier::active()->orderBy('name')->get()
                    ->map(fn ($s) => [
                        'name' => $s->name,
                        'address' => (string) $s->address,
                        'type' => 'Supplier',
                        'sub' => null,
                        'lat' => $s->latitude,
                        'lng' => $s->longitude,
                    ]);
                // Inventory only stores a free-text address, so work out the
                // city/province/postal code from it when the address names a known city.
                $placeOptions = $locations->concat($suppliers)
                    ->map(fn ($p) => $p + $this->cityFromAddress($p['address']))
                    ->values()->all();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return view('shipments.create', [
            'placeOptions' => $placeOptions,
            'stockOptions' => $stockOptions,
            'inventoryDown' => $inventoryDown,
            // Only drivers who are working and vehicles that aren't in the workshop.
            'drivers' => Driver::where('status', 'active')->orderBy('name')->get(),
            'vehicles' => Vehicle::where('status', '!=', 'maintenance')->orderBy('plate_number')->get(),
        ]);
    }

    /**
     * Find the city named in a free-text address using resources/data/ph_locations.json.
     * The longest matching city name wins ("Quezon City" over "Quezon"), and a postal
     * code written in the address is kept over the dataset's default one.
     *
     * @return array{city?:string, province?:string, postal_code?:string}
     */
    private function cityFromAddress(string $address): array
    {
        if (trim($address) === '') {
            return [];
        }

        static $cities = null;
        $cities ??= collect(json_decode(file_get_contents(resource_path('data/ph_locations.json')), true) ?? [])
            ->sortByDesc(fn ($row) => mb_strlen($row['city']))
            ->values();

        $match = $cities->first(fn ($row) => preg_match(
            '/(^|[^\p{L}])'.preg_quote($row['city'], '/').'($|[^\p{L}])/iu',
            $address
        ));

        if (! $match) {
            return [];
        }

        if (preg_match('/\b(\d{4})\b/', $address, $postal)) {
            $match['postal_code'] = $postal[1];
        }

        return [
            'city' => $match['city'],
            'province' => $match['province'],
            'postal_code' => $match['postal_code'],
        ];
    }

    public function store(Request $request, Geocoder $geocoder, StockReservations $reservations)
    {
        // Extra pickup stops (pickup2_*, pickup3_*, ...): each is optional, but once any part
        // of a stop is filled in, its name, address and city are all needed.
        $pickupRules = $pickupNames = [];
        foreach (range(2, Shipment::MAX_PICKUPS) as $n) {
            $p = "pickup{$n}";
            $pickupRules += [
                "{$p}_name" => ['nullable', "required_with:{$p}_address,{$p}_city", 'string', 'max:150'],
                "{$p}_address" => ['nullable', "required_with:{$p}_name,{$p}_city", 'string', 'max:255'],
                "{$p}_city" => ['nullable', "required_with:{$p}_name,{$p}_address", 'string', 'max:100'],
                "{$p}_province" => ['nullable', 'string', 'max:100'],
                "{$p}_postal_code" => ['nullable', 'string', 'max:20'],
                "{$p}_scheduled_at" => ['nullable', 'date', 'after:now', 'before_or_equal:scheduled_delivery_at'],
                // Set by the form when a pinned Inventory location/supplier is picked.
                "{$p}_latitude" => ['nullable', 'numeric', 'between:-90,90'],
                "{$p}_longitude" => ['nullable', 'numeric', 'between:-180,180'],
            ];
            foreach (['name', 'address', 'city', 'province', 'postal_code'] as $part) {
                $pickupNames["{$p}_{$part}"] = "pickup {$n} ".str_replace('_', ' ', $part);
            }
            $pickupNames["{$p}_scheduled_at"] = "pickup {$n} time";
        }
        $pickupNames['scheduled_delivery_at'] = 'scheduled delivery';

        $data = $request->validate($pickupRules + [
            'origin_name' => ['required', 'string', 'max:150'],
            'origin_address' => ['required', 'string', 'max:255'],
            'origin_city' => ['required', 'string', 'max:100'],
            'origin_province' => ['nullable', 'string', 'max:100'],
            'origin_postal_code' => ['nullable', 'string', 'max:20'],
            'destination_name' => ['required', 'string', 'max:150'],
            'destination_address' => ['required', 'string', 'max:255'],
            'destination_city' => ['required', 'string', 'max:100'],
            'destination_province' => ['nullable', 'string', 'max:100'],
            'destination_postal_code' => ['nullable', 'string', 'max:20'],
            'scheduled_pickup_at' => ['required', 'date', 'after:now'],
            'scheduled_delivery_at' => ['required', 'date', 'after:now', 'after_or_equal:scheduled_pickup_at'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.key' => ['required', 'string', 'regex:/^\d+:\d+$/'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            // Which pickup stop on the form the line belongs to (1 is the origin).
            'items.*.pickup' => ['nullable', 'integer', 'between:1,'.Shipment::MAX_PICKUPS],
            // Set by the form when a pinned Inventory location/supplier is picked.
            'origin_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'origin_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'destination_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'scheduled_pickup_at.required' => 'Enter the scheduled pickup date and time.',
            'scheduled_pickup_at.after' => 'The scheduled pickup must be in the future.',
            'scheduled_delivery_at.required' => 'Enter the scheduled delivery date and time.',
            'scheduled_delivery_at.after' => 'The scheduled delivery must be in the future.',
            'scheduled_delivery_at.after_or_equal' => "The scheduled delivery can't be earlier than the scheduled pickup.",
        ], $pickupNames);

        // Pull the extra stops out of the validated data, skipping any left blank.
        $extraPickups = [];
        $stopNumbers = [1 => 1]; // the form's stop number => the saved sequence (blank stops leave gaps)
        foreach (range(2, Shipment::MAX_PICKUPS) as $n) {
            $p = "pickup{$n}";
            if (filled($data["{$p}_name"] ?? null)) {
                $stopNumbers[$n] = count($extraPickups) + 2;
                $extraPickups[] = [
                    'latitude' => $data["{$p}_latitude"] ?? null,
                    'longitude' => $data["{$p}_longitude"] ?? null,
                    'name' => $data["{$p}_name"],
                    'address' => $data["{$p}_address"],
                    'city' => $data["{$p}_city"],
                    'province' => $data["{$p}_province"] ?? null,
                    'postal_code' => $data["{$p}_postal_code"] ?? null,
                    'scheduled_at' => $data["{$p}_scheduled_at"] ?? null,
                ];
            }
        }
        $data = array_diff_key($data, $pickupRules);

        // Check stock up front so a shortage is reported before the slower map lookup;
        // it's checked again below, under the lock, right before saving.
        $this->resolveItems($data['items'], $reservations->reserved());

        // Anything not pinned in Inventory gets looked up from its address for the route map.
        foreach (['origin', 'destination'] as $end) {
            if (empty($data["{$end}_latitude"]) || empty($data["{$end}_longitude"])) {
                $point = $geocoder->locate($data["{$end}_address"], $data["{$end}_city"], $data["{$end}_province"] ?? null);
                [$data["{$end}_latitude"], $data["{$end}_longitude"]] = $point ?? [null, null];
            }
        }
        foreach ($extraPickups as &$stop) {
            if (empty($stop['latitude']) || empty($stop['longitude'])) {
                [$stop['latitude'], $stop['longitude']] = $geocoder->locate($stop['address'], $stop['city'], $stop['province']) ?? [null, null];
            }
        }
        unset($stop);

        // One shipment at a time from here: re-check what's free and save before anyone
        // else can reserve the same stock.
        try {
            [$shipment, $lines] = $reservations->exclusively(function () use ($data, $extraPickups, $stopNumbers, $request, $reservations) {
                $lines = $this->resolveItems($data['items'], $reservations->reserved());

                $shipment = DB::transaction(function () use ($data, $extraPickups, $stopNumbers, $lines, $request) {
                    $shipment = Shipment::create(collect($data)->except('items')->all() + [
                        'tracking_number' => $this->newTrackingNumber(),
                        'created_by' => $request->user()->id,
                        'status' => 'pending',
                    ]);
                    // Each item remembers its stop only when there are several; a line whose
                    // stop was left blank falls back to the origin.
                    $shipment->items()->createMany(array_map(fn ($line) => collect($line)->except('pickup')->all() + [
                        'pickup_sequence' => $extraPickups ? ($stopNumbers[$line['pickup']] ?? 1) : null,
                    ], $lines));
                    // Several pickups: list every stop in order, starting with the origin.
                    if ($extraPickups) {
                        $first = [
                            'name' => $data['origin_name'], 'address' => $data['origin_address'], 'city' => $data['origin_city'],
                            'province' => $data['origin_province'] ?? null, 'postal_code' => $data['origin_postal_code'] ?? null,
                            'latitude' => $data['origin_latitude'] ?? null, 'longitude' => $data['origin_longitude'] ?? null,
                            'scheduled_at' => $data['scheduled_pickup_at'],
                        ];
                        foreach ([$first, ...$extraPickups] as $i => $stop) {
                            $shipment->pickups()->create($stop + ['sequence' => $i + 1]);
                        }
                    }
                    $shipment->history()->create([
                        'status' => 'pending',
                        'note' => 'Shipment created',
                        'changed_by' => $request->user()->id,
                        'changed_at' => now(),
                    ]);

                    return $shipment;
                });

                return [$shipment, $lines];
            });
        } catch (LockTimeoutException) {
            return back()->withInput()->with('error', 'Another shipment is being saved right now. Please try again in a moment.');
        }

        ActivityLog::record('created', sprintf(
            'Created shipment %s to %s with %d item line(s), %d unit(s)',
            $shipment->tracking_number, $shipment->destination_city, count($lines), array_sum(array_column($lines, 'quantity'))
        ), $shipment);

        if ($shipment->driver_id) {
            app(ShipmentAlerts::class)->driversAssigned($shipment, [$shipment->driver_id], $request->user()->id);
        }

        return redirect()->route('shipments.show', $shipment)->with('status', "Shipment {$shipment->tracking_number} created.");
    }

    public function show(Shipment $shipment, Geocoder $geocoder)
    {
        // Shipments created before the route map existed get their coordinates on first view.
        if ($geocoder->fillShipment($shipment)) {
            $shipment->saveQuietly();
        }

        $shipment->load(['items', 'history', 'driver', 'vehicle', 'allocations.vehicle', 'allocations.driver', 'shipmentNotes.user.avatar', 'pickups.collector']);

        // Office roles see where the vehicle is, while it's on the road and the driver shares.
        $user = request()->user();
        $live = ($user->isSuperAdmin() || $user->hasRole('manager', 'logistics_coordinator'))
            && in_array($shipment->status, TrackingController::TRACKABLE_STATUSES, true)
            // One per driver, so each truck of a split shipment shows.
            ? VehicleLocationPing::with(['driver', 'vehicle'])->whereIn('id', TrackingController::latestPingIds($shipment->shipment_id))->get()
            : collect();

        return view('shipments.show', ['shipment' => $shipment, 'live' => $live]);
    }

    /** Printable copy of one shipment. The browser's print dialog saves it as a PDF. */
    public function print(Shipment $shipment)
    {
        $shipment->load(['items', 'history', 'driver', 'vehicle', 'allocations.vehicle', 'allocations.driver', 'pickups']);

        ActivityLog::record('report', "Printed shipment {$shipment->tracking_number}", $shipment);

        return view('shipments.print', ['shipment' => $shipment]);
    }

    /** Printable copy of the shipment list, with the same search and status filter as the page. */
    public function printList(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));
        $query = $this->listQuery($status, $search);

        $total = (clone $query)->count();

        ActivityLog::record('report', "Printed the shipment list ({$total} matching shipments)");

        return view('shipments.print_list', [
            'shipments' => $query->limit(self::PRINT_LIMIT)->get(),
            'total' => $total,
            'limit' => self::PRINT_LIMIT,
            'status' => $status === 'open' || in_array($status, Shipment::STATUSES, true) ? $status : null,
            'search' => $search,
        ]);
    }

    /** Shipments matching the list page's search and status filter, newest first. */
    private function listQuery(?string $status, string $search)
    {
        return Shipment::query()
            // "open" is every status that isn't finished yet (the reminder banner links here).
            ->when($status === 'open', fn ($q) => $q->whereIn('status', Shipment::OPEN_STATUSES))
            ->when(in_array($status, Shipment::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('tracking_number', 'like', "%{$search}%")
                ->orWhere('destination_name', 'like', "%{$search}%")
                ->orWhere('destination_city', 'like', "%{$search}%")))
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->orderByDesc('shipment_id');
    }

    public function updateStatus(Request $request, Shipment $shipment)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Shipment::STATUSES)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! in_array($data['status'], $shipment->allowedNextStatuses(), true)) {
            return back()->with('error', "A {$shipment->statusLabel()} shipment can't be changed to ".Shipment::label($data['status']).'.');
        }
        if ($blocked = $this->pickupsStillToCollect($shipment, $data['status'])) {
            return back()->with('error', $blocked);
        }

        $this->applyStatus($shipment, $data['status'], $data['note'] ?? null, $request->user()->id);

        return back()->with('status', 'Status updated to '.Shipment::label($data['status']).'.');
    }

    /**
     * Field Personnel updating a delivery assigned to them. A problem (Delayed, Delivery
     * attempted) needs a reason; Delivered needs a photo and who received it. Office staff use updateStatus.
     */
    public function fieldUpdate(Request $request, Shipment $shipment)
    {
        $user = $request->user();
        abort_unless($user->hasRole('field_personnel') && $shipment->isAssignedToDriver($user->driver), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in($shipment->fieldNextStatuses())],
            'note' => [Rule::requiredIf(in_array($request->input('status'), Shipment::PROBLEM_STATUSES, true)), 'nullable', 'string', 'max:500'],
            'received_by' => [Rule::requiredIf($request->input('status') === 'delivered'), 'nullable', 'string', 'max:150'],
            // Photo proof, from the phone camera or gallery. mimes checks the real file content.
            'proof_photo' => [Rule::requiredIf($request->input('status') === 'delivered'), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'status.in' => "This shipment can't be changed to that status from the field.",
            'note.required' => 'Please say what went wrong.',
            'received_by.required' => 'Enter the name of the person who received the delivery.',
            'proof_photo.required' => 'Take or attach a photo as proof of delivery.',
        ]);

        if ($blocked = $this->pickupsStillToCollect($shipment, $data['status'])) {
            return back()->withErrors(['status' => $blocked])->withInput();
        }

        $extra = [];
        if ($data['status'] === 'delivered') {
            $extra = [
                'proof_photo_path' => $request->file('proof_photo')->store('delivery-proofs', 'public'),
                'received_by' => $data['received_by'],
            ];
        }

        $note = $data['note'] ?? null;
        if ($data['status'] === 'delivered') {
            $note = trim("Received by {$data['received_by']}. ".($note ?? ''));
        }

        try {
            $this->applyStatus($shipment, $data['status'], $note, $user->id, $extra);
        } catch (\Throwable $e) {
            if (isset($extra['proof_photo_path'])) {
                Storage::disk('public')->delete($extra['proof_photo_path']);
            }
            throw $e;
        }

        return back()->with('status', __('Delivery updated: :status.', ['status' => __(Shipment::label($data['status']))])
            .($data['status'] === 'delivered' ? ' '.__('Location sharing has stopped because the delivery is finished.') : ''));
    }

    /**
     * Confirm that one pickup stop has been collected. Open to office staff and to the
     * driver assigned to the shipment. Once every stop is collected, a shipment that was
     * Ready for pickup moves to Picked up by itself.
     */
    public function collectPickup(Request $request, Shipment $shipment, ShipmentPickup $pickup)
    {
        $user = $request->user();
        $isOffice = $user->isSuperAdmin() || $user->hasRole('manager', 'logistics_coordinator');
        abort_unless($isOffice || $shipment->isAssignedToDriver($user->driver), 403);
        abort_unless((int) $pickup->shipment_id === (int) $shipment->shipment_id, 404);

        if ($shipment->status === 'pending' || ! in_array($shipment->status, Shipment::OPEN_STATUSES, true)) {
            return back()->with('error', $shipment->status === 'pending'
                ? __('This shipment has not been released for pickup yet.')
                : __('This shipment is finished, so its pickups can no longer be changed.'));
        }
        if ($pickup->isCollected()) {
            return back()->with('status', __('That pickup was already collected.'));
        }

        $total = $shipment->pickups()->count();
        DB::transaction(function () use ($shipment, $pickup, $user, $total) {
            $pickup->update(['picked_up_at' => now(), 'picked_up_by' => $user->id]);
            $shipment->history()->create([
                'status' => $shipment->status,
                'note' => "Collected pickup {$pickup->sequence} of {$total}: {$pickup->name}",
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);
        });

        ActivityLog::record('status_changed', "Shipment {$shipment->tracking_number}: collected pickup {$pickup->sequence} of {$total} ({$pickup->name})", $shipment);

        $left = $shipment->pickups()->whereNull('picked_up_at')->count();
        if ($left === 0 && $shipment->status === 'ready_for_pickup') {
            $this->applyStatus($shipment, 'picked_up', 'All pickups collected.', $user->id);
        }

        return back()->with('status', $left
            ? trans_choice('Pickup collected. :count more to collect.|Pickup collected. :count more to collect.', $left)
            : __('All pickups collected.'));
    }

    /**
     * With several pickup stops, a shipment can't be called picked up, out for delivery
     * or delivered while any stop is still to be collected. Returns the reason, or null.
     */
    private function pickupsStillToCollect(Shipment $shipment, string $status): ?string
    {
        if (! in_array($status, ['picked_up', 'out_for_delivery', 'delivered'], true)) {
            return null;
        }

        $left = $shipment->pickups()->whereNull('picked_up_at')->count();

        return $left ? trans_choice('Collect every pickup first: :count stop is still to be collected.|Collect every pickup first: :count stops are still to be collected.', $left) : null;
    }

    /** Change a shipment's status, add it to the history and the activity log. */
    private function applyStatus(Shipment $shipment, string $status, ?string $note, int $userId, array $extra = []): void
    {
        $from = $shipment->statusLabel();

        DB::transaction(function () use ($shipment, $status, $note, $userId, $extra) {
            $shipment->fill($extra);
            $shipment->status = $status;
            if (in_array($status, Shipment::ON_ROAD_STATUSES, true) && ! $shipment->dispatched_at) {
                $shipment->dispatched_at = now();
            }
            if ($status === 'delivered') {
                $shipment->actual_delivery_at = now();
            }
            $shipment->updated_at = now();
            $shipment->save();

            $shipment->history()->create([
                'status' => $status,
                'note' => $note,
                'changed_by' => $userId,
                'changed_at' => now(),
            ]);
        });

        ActivityLog::record('status_changed', sprintf(
            'Shipment %s: %s to %s%s', $shipment->tracking_number, $from, Shipment::label($status),
            $note ? ' ("'.$note.'")' : ''
        ), $shipment);

        app(ShipmentAlerts::class)->statusChanged($shipment, $status, $note, $userId);
    }

    /**
     * Auto-allocate this shipment across the available fleet (First-Fit-Decreasing)
     * and dispatch it. See App\Services\Fleet\FleetAllocationService for the algorithm.
     */
    public function dispatch(Request $request, Shipment $shipment, FleetAllocationService $fleet)
    {
        if (! in_array($shipment->status, ['pending', 'ready_for_pickup'], true)) {
            return back()->with('error', "Only a Pending or Ready for pickup shipment can be auto-dispatched (this one is {$shipment->statusLabel()}).");
        }

        try {
            $plan = $fleet->dispatch($shipment, $request->user()->id);
        } catch (InsufficientFleetCapacityException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        app(ShipmentAlerts::class)->driversAssigned($shipment, $shipment->allocations()->pluck('driver_id')->all(), $request->user()->id);

        $message = $plan->isSplit()
            ? "Shipment {$shipment->tracking_number} split across {$plan->vehicleCount()} vehicles and dispatched."
            : "Shipment {$shipment->tracking_number} dispatched on {$plan->legs[0]->vehicle->plate_number}.";

        return back()->with('status', $message);
    }

    /**
     * Turn the submitted lines into rows, taking SKU, name and available stock from the
     * Inventory database rather than trusting the browser. Duplicate lines at the same
     * pickup are merged. Each row carries 'pickup': the form's stop number it belongs to
     * (1 is the origin), which store() turns into the saved stop sequence.
     * $reserved is StockReservations::reserved(): stock already promised to open shipments.
     */
    private function resolveItems(array $items, array $reserved): array
    {
        $wanted = [];
        $totals = []; // per product/location, across every pickup: what the stock check counts
        foreach ($items as $item) {
            $pickup = max(1, (int) ($item['pickup'] ?? 1));
            $id = "{$item['key']}@{$pickup}";
            $wanted[$id] ??= ['key' => $item['key'], 'pickup' => $pickup, 'quantity' => 0];
            $wanted[$id]['quantity'] += (int) $item['quantity'];
            $totals[$item['key']] = ($totals[$item['key']] ?? 0) + (int) $item['quantity'];
            // Later lines for the same product win if they set a weight; keeps this simple
            // since duplicate lines for one product are an edge case, not the normal path.
            if (! empty($item['unit_weight_kg'])) {
                $wanted[$id]['unit_weight_kg'] = (float) $item['unit_weight_kg'];
            }
        }

        $lines = [];
        $errors = [];
        $stocks = []; // per product/location: the stock row, or null once it has failed a check
        foreach ($wanted as $wantedLine) {
            $key = $wantedLine['key'];
            [$productId, $locationId] = array_map('intval', explode(':', $key));

            if (! array_key_exists($key, $stocks)) {
                $stock = Stock::with('product')->where('product_id', $productId)->where('location_id', $locationId)->first();
                $stocks[$key] = null;

                if (! $stock || ! $stock->product || ! $stock->product->is_active) {
                    $errors[] = 'One of the selected products is no longer available.';

                    continue;
                }
                $qty = $totals[$key];
                $onHand = (int) $stock->quantity;
                $held = $reserved[$key] ?? 0;
                $free = max(0, $onHand - $held);
                if ($qty > $free) {
                    $errors[] = $held > 0
                        ? "{$stock->product->name}: only {$free} available ({$onHand} in stock, {$held} reserved for other shipments), but {$qty} requested."
                        : "{$stock->product->name}: only {$onHand} in stock, but {$qty} requested.";

                    continue;
                }
                $stocks[$key] = $stock;
            }

            if (! $stock = $stocks[$key]) {
                continue;
            }
            $lines[] = [
                'inventory_product_id' => $productId,
                'inventory_location_id' => $locationId,
                'sku' => $stock->product->sku,
                'item_name' => $stock->product->name,
                'quantity' => $wantedLine['quantity'],
                'unit_weight_kg' => $wantedLine['unit_weight_kg'] ?? null,
                'pickup' => $wantedLine['pickup'],
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages(['items' => $errors]);
        }

        return $lines;
    }

    private function newTrackingNumber(): string
    {
        do {
            $number = 'SH-'.now()->format('Ymd').'-'.random_int(10000, 99999);
        } while (Shipment::where('tracking_number', $number)->exists());

        return $number;
    }
}
