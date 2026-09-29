<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientFleetCapacityException;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Inventory\Location;
use App\Models\Inventory\Stock;
use App\Models\Inventory\Supplier;
use App\Models\Shipment;
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
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $shipments = Shipment::query()
            ->when(in_array($status, Shipment::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('tracking_number', 'like', "%{$search}%")
                ->orWhere('destination_name', 'like', "%{$search}%")
                ->orWhere('destination_city', 'like', "%{$search}%")))
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->orderByDesc('shipment_id')
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
        $data = $request->validate([
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
            'scheduled_delivery_at' => ['required', 'date', 'after:now'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.key' => ['required', 'string', 'regex:/^\d+:\d+$/'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            // Set by the form when a pinned Inventory location/supplier is picked.
            'origin_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'origin_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'destination_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

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

        // One shipment at a time from here: re-check what's free and save before anyone
        // else can reserve the same stock.
        try {
            [$shipment, $lines] = $reservations->exclusively(function () use ($data, $request, $reservations) {
                $lines = $this->resolveItems($data['items'], $reservations->reserved());

                $shipment = DB::transaction(function () use ($data, $lines, $request) {
                    $shipment = Shipment::create(collect($data)->except('items')->all() + [
                        'tracking_number' => $this->newTrackingNumber(),
                        'created_by' => $request->user()->id,
                        'status' => 'pending',
                    ]);
                    $shipment->items()->createMany($lines);
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

        $shipment->load(['items', 'history', 'driver', 'vehicle', 'allocations.vehicle', 'allocations.driver', 'shipmentNotes.user.avatar']);

        // Office roles see where the vehicle is, while it's on the road and the driver shares.
        $user = request()->user();
        $live = ($user->isSuperAdmin() || $user->hasRole('manager', 'logistics_coordinator'))
            && in_array($shipment->status, TrackingController::TRACKABLE_STATUSES, true)
            // One per driver, so each truck of a split shipment shows.
            ? VehicleLocationPing::with(['driver', 'vehicle'])->whereIn('id', TrackingController::latestPingIds($shipment->shipment_id))->get()
            : collect();

        return view('shipments.show', ['shipment' => $shipment, 'live' => $live]);
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

        $this->applyStatus($shipment, $data['status'], $data['note'] ?? null, $request->user()->id);

        return back()->with('status', 'Status updated to '.Shipment::label($data['status']).'.');
    }

    /**
     * Field Personnel updating a delivery assigned to them: In transit, Delayed (with a
     * reason) or Delivered (with a photo and who received it). Office staff use updateStatus.
     */
    public function fieldUpdate(Request $request, Shipment $shipment)
    {
        $user = $request->user();
        abort_unless($user->hasRole('field_personnel') && $shipment->isAssignedToDriver($user->driver), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in($shipment->fieldNextStatuses())],
            'note' => [Rule::requiredIf($request->input('status') === 'delayed'), 'nullable', 'string', 'max:500'],
            'received_by' => [Rule::requiredIf($request->input('status') === 'delivered'), 'nullable', 'string', 'max:150'],
            // Photo proof, from the phone camera or gallery. mimes checks the real file content.
            'proof_photo' => [Rule::requiredIf($request->input('status') === 'delivered'), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'status.in' => "This shipment can't be changed to that status from the field.",
            'note.required' => 'Please give a reason for the delay.',
            'received_by.required' => 'Enter the name of the person who received the delivery.',
            'proof_photo.required' => 'Take or attach a photo as proof of delivery.',
        ]);

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

    /** Change a shipment's status, add it to the history and the activity log. */
    private function applyStatus(Shipment $shipment, string $status, ?string $note, int $userId, array $extra = []): void
    {
        $from = $shipment->statusLabel();

        DB::transaction(function () use ($shipment, $status, $note, $userId, $extra) {
            $shipment->fill($extra);
            $shipment->status = $status;
            if ($status === 'in_transit' && ! $shipment->dispatched_at) {
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
            'Shipment %s: %s → %s%s', $shipment->tracking_number, $from, Shipment::label($status),
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
        if ($shipment->status !== 'pending') {
            return back()->with('error', "Only a Pending shipment can be auto-dispatched (this one is {$shipment->statusLabel()}).");
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
     * Inventory database rather than trusting the browser. Duplicate lines are merged.
     * $reserved is StockReservations::reserved(): stock already promised to open shipments.
     */
    private function resolveItems(array $items, array $reserved): array
    {
        $wanted = [];
        foreach ($items as $item) {
            $wanted[$item['key']]['quantity'] = ($wanted[$item['key']]['quantity'] ?? 0) + (int) $item['quantity'];
            // Later lines for the same product win if they set a weight; keeps this simple
            // since duplicate lines for one product are an edge case, not the normal path.
            if (! empty($item['unit_weight_kg'])) {
                $wanted[$item['key']]['unit_weight_kg'] = (float) $item['unit_weight_kg'];
            }
        }

        $lines = [];
        $errors = [];
        foreach ($wanted as $key => $wantedLine) {
            $qty = $wantedLine['quantity'];
            [$productId, $locationId] = array_map('intval', explode(':', $key));
            $stock = Stock::with('product')->where('product_id', $productId)->where('location_id', $locationId)->first();

            if (! $stock || ! $stock->product || ! $stock->product->is_active) {
                $errors[] = 'One of the selected products is no longer available.';

                continue;
            }
            $onHand = (int) $stock->quantity;
            $held = $reserved[$key] ?? 0;
            $free = max(0, $onHand - $held);
            if ($qty > $free) {
                $errors[] = $held > 0
                    ? "{$stock->product->name}: only {$free} available ({$onHand} in stock, {$held} reserved for other shipments), but {$qty} requested."
                    : "{$stock->product->name}: only {$onHand} in stock, but {$qty} requested.";

                continue;
            }
            $lines[] = [
                'inventory_product_id' => $productId,
                'inventory_location_id' => $locationId,
                'sku' => $stock->product->sku,
                'item_name' => $stock->product->name,
                'quantity' => $qty,
                'unit_weight_kg' => $wantedLine['unit_weight_kg'] ?? null,
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
