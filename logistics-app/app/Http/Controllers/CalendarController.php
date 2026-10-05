<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Pickups and deliveries laid out by day. A delivery sits on its scheduled delivery
 * time (the ETA typed in when the shipment was created); a pickup sits on its scheduled
 * pickup time, or on the dispatch time when none was entered. Field Personnel see only
 * the shipments assigned to them.
 */
class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isField = $user->hasRole('field_personnel');

        $view = $request->query('view') === 'week' ? 'week' : 'month';
        $anchor = $this->dateFrom($request->query('date')) ?? today();

        [$start, $end] = $view === 'week'
            ? [$anchor->copy()->startOfWeek(CarbonInterface::SUNDAY), $anchor->copy()->endOfWeek(CarbonInterface::SATURDAY)]
            : [$anchor->copy()->startOfMonth()->startOfWeek(CarbonInterface::SUNDAY), $anchor->copy()->endOfMonth()->endOfWeek(CarbonInterface::SATURDAY)];

        // Filters are for office staff; a driver's calendar is already just their own work.
        $status = $isField ? null : $request->query('status');
        $driverId = $isField ? null : ((int) $request->query('driver_id') ?: null);
        $vehicleId = $isField ? null : ((int) $request->query('vehicle_id') ?: null);

        $shipments = collect();
        if (! $isField || $user->driver) {
            $shipments = Shipment::query()
                ->with(['driver', 'vehicle', 'allocations.vehicle', 'allocations.driver'])
                ->where(fn ($q) => $q
                    ->whereBetween('scheduled_delivery_at', [$start, $end])
                    ->orWhereBetween('scheduled_pickup_at', [$start, $end])
                    ->orWhere(fn ($p) => $p->whereNull('scheduled_pickup_at')->whereBetween('dispatched_at', [$start, $end])))
                ->when($isField, fn ($q) => $q->assignedToDriver($user->driver->id))
                ->when(in_array($status, Shipment::STATUSES, true), fn ($q) => $q->where('status', $status))
                ->when($driverId, fn ($q) => $q->assignedToDriver($driverId))
                ->when($vehicleId, fn ($q) => $q->where(fn ($w) => $w
                    ->where('vehicle_id', $vehicleId)
                    ->orWhereHas('allocations', fn ($a) => $a->where('vehicle_id', $vehicleId))))
                ->get();
        }

        $events = $this->events($shipments, $start, $end);

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = $d->copy();
        }

        $selected = $this->dateFrom($request->query('day'));
        if (! $selected || ! $selected->betweenIncluded($start, $end)) {
            $selected = today()->betweenIncluded($start, $end) ? today() : $anchor->copy();
        }

        $step = $view === 'week' ? 'week' : 'month';
        $base = $view === 'week' ? $anchor->copy() : $anchor->copy()->startOfMonth();

        return view('calendar.index', [
            'view' => $view,
            'anchor' => $anchor,
            'title' => $view === 'week'
                ? $start->translatedFormat('M j').' – '.$end->translatedFormat('M j, Y')
                : $anchor->translatedFormat('F Y'),
            'days' => $days,
            'events' => $events,
            'selected' => $selected,
            'prevDate' => $base->copy()->sub(1, $step)->toDateString(),
            'nextDate' => $base->copy()->add(1, $step)->toDateString(),
            'isField' => $isField,
            'isLinkedDriver' => (bool) $user->driver,
            'status' => $status,
            'driverId' => $driverId,
            'vehicleId' => $vehicleId,
            'drivers' => $isField ? collect() : Driver::orderBy('name')->get(['id', 'name']),
            'vehicles' => $isField ? collect() : Vehicle::orderBy('plate_number')->get(['id', 'plate_number']),
        ]);
    }

    /** Pickup and delivery entries inside the range, keyed by day (Y-m-d) and sorted by time. */
    private function events($shipments, Carbon $start, Carbon $end): array
    {
        $events = [];
        foreach ($shipments as $s) {
            $pickupAt = $s->scheduled_pickup_at ?? $s->dispatched_at;
            if ($pickupAt && $pickupAt->betweenIncluded($start, $end)) {
                $events[$pickupAt->toDateString()][] = [
                    'type' => 'pickup',
                    'label' => $s->scheduled_pickup_at ? 'Pickup' : 'Dispatched',
                    'at' => $pickupAt,
                    'shipment' => $s,
                    'place' => $s->origin_name,
                    'address' => $s->origin_address,
                    'city' => $s->origin_city,
                    'overdue' => false,
                ];
            }

            $deliveryAt = $s->scheduled_delivery_at;
            if ($deliveryAt && $deliveryAt->betweenIncluded($start, $end)) {
                $events[$deliveryAt->toDateString()][] = [
                    'type' => 'delivery',
                    'label' => 'Delivery',
                    'at' => $deliveryAt,
                    'shipment' => $s,
                    'place' => $s->destination_name,
                    'address' => $s->destination_address,
                    'city' => $s->destination_city,
                    'overdue' => $deliveryAt->isPast() && in_array($s->status, Shipment::OPEN_STATUSES, true),
                ];
            }
        }

        foreach ($events as &$day) {
            usort($day, fn ($a, $b) => $a['at'] <=> $b['at']);
        }

        return $events;
    }

    private function dateFrom(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
