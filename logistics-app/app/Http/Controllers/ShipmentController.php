<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Inventory\Stock;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        return view('shipments.index', ['shipments' => $shipments, 'status' => $status, 'search' => $search]);
    }

    public function create()
    {
        $stockOptions = [];
        $inventoryDown = false;
        try {
            // Only stock that actually exists, for active products.
            $stockOptions = Stock::with(['product', 'location'])
                ->where('quantity', '>', 0)
                ->get()
                ->filter(fn ($s) => $s->product && $s->product->is_active && $s->location)
                ->map(fn ($s) => [
                    'key' => "{$s->product_id}:{$s->location_id}",
                    'label' => "{$s->product->name} ({$s->product->code}) – {$s->location->name}",
                    'available' => (int) $s->quantity,
                ])->sortBy('label')->values()->all();
        } catch (\Throwable $e) {
            report($e);
            $inventoryDown = true;
        }

        return view('shipments.create', [
            'stockOptions' => $stockOptions,
            'inventoryDown' => $inventoryDown,
            // Only drivers who are working and vehicles that aren't in the workshop.
            'drivers' => Driver::where('status', 'active')->orderBy('name')->get(),
            'vehicles' => Vehicle::where('status', '!=', 'maintenance')->orderBy('plate_number')->get(),
        ]);
    }

    public function store(Request $request)
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
        ]);

        $lines = $this->resolveItems($data['items']);

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

        ActivityLog::record('created', sprintf(
            'Created shipment %s to %s with %d item line(s), %d unit(s)',
            $shipment->tracking_number, $shipment->destination_city, count($lines), array_sum(array_column($lines, 'quantity'))
        ), $shipment);

        return redirect()->route('shipments.show', $shipment)->with('status', "Shipment {$shipment->tracking_number} created.");
    }

    public function show(Shipment $shipment)
    {
        $shipment->load(['items', 'history', 'driver', 'vehicle']);

        return view('shipments.show', ['shipment' => $shipment]);
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

        $from = $shipment->statusLabel();

        DB::transaction(function () use ($shipment, $data, $request) {
            $shipment->status = $data['status'];
            if ($data['status'] === 'in_transit' && ! $shipment->dispatched_at) {
                $shipment->dispatched_at = now();
            }
            if ($data['status'] === 'delivered') {
                $shipment->actual_delivery_at = now();
            }
            $shipment->updated_at = now();
            $shipment->save();

            $shipment->history()->create([
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);
        });

        ActivityLog::record('status_changed', sprintf(
            'Shipment %s: %s → %s%s', $shipment->tracking_number, $from, Shipment::label($data['status']),
            ! empty($data['note']) ? ' ("'.$data['note'].'")' : ''
        ), $shipment);

        return back()->with('status', 'Status updated to '.Shipment::label($data['status']).'.');
    }

    /**
     * Turn the submitted lines into rows, taking SKU, name and available stock from the
     * Inventory database rather than trusting the browser. Duplicate lines are merged.
     */
    private function resolveItems(array $items): array
    {
        $wanted = [];
        foreach ($items as $item) {
            $wanted[$item['key']] = ($wanted[$item['key']] ?? 0) + (int) $item['quantity'];
        }

        $lines = [];
        $errors = [];
        foreach ($wanted as $key => $qty) {
            [$productId, $locationId] = array_map('intval', explode(':', $key));
            $stock = Stock::with('product')->where('product_id', $productId)->where('location_id', $locationId)->first();

            if (! $stock || ! $stock->product || ! $stock->product->is_active) {
                $errors[] = 'One of the selected products is no longer available.';
                continue;
            }
            if ($qty > (int) $stock->quantity) {
                $errors[] = "{$stock->product->name}: only {$stock->quantity} in stock, but {$qty} requested.";
                continue;
            }
            $lines[] = [
                'inventory_product_id' => $productId,
                'inventory_location_id' => $locationId,
                'sku' => $stock->product->code,
                'item_name' => $stock->product->name,
                'quantity' => $qty,
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
