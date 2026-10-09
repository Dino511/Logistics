<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentVehicleAllocation;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

/**
 * Which vehicles, drivers and helpers are tied up on a shipment that isn't finished yet.
 * Nothing is stored: a vehicle, driver or helper is busy while any shipment they are on
 * is still open, and free again the moment it is Delivered, Returned or Cancelled.
 */
class CrewAvailability
{
    /**
     * The open shipment each busy vehicle, driver and helper is on, keyed by their id.
     * Pass a shipment id to leave that shipment's own crew out (e.g. while dispatching it).
     *
     * @return array{vehicles: array<int, Shipment>, drivers: array<int, Shipment>, helpers: array<int, Shipment>}
     */
    public function busy(?int $exceptShipmentId = null): array
    {
        $busy = ['vehicles' => [], 'drivers' => [], 'helpers' => []];

        $open = Shipment::with('allocations')
            ->whereIn('status', Shipment::OPEN_STATUSES)
            ->when($exceptShipmentId, fn ($q) => $q->where('shipment_id', '!=', $exceptShipmentId))
            ->orderBy('shipment_id')
            ->get();

        foreach ($open as $shipment) {
            // The shipment's own crew, plus every truck and driver of a split load.
            $on = [
                'vehicles' => [$shipment->vehicle_id, ...$shipment->allocations->pluck('vehicle_id')],
                'drivers' => [$shipment->driver_id, ...$shipment->allocations->pluck('driver_id')],
                'helpers' => [$shipment->helper_id],
            ];
            foreach ($on as $kind => $ids) {
                foreach (array_filter($ids) as $id) {
                    $busy[$kind][(int) $id] ??= $shipment;
                }
            }
        }

        return $busy;
    }

    /**
     * Refuse a shipment that asks for a vehicle, driver or helper still out on
     * another one. $picked is ['vehicle_id' => [id, name], ...]. Pass the shipment's own
     * id when changing the crew of an existing shipment, so it doesn't block itself.
     *
     * @param  array<string, array{0: int|string|null, 1: ?string}>  $picked
     *
     * @throws ValidationException
     */
    public function assertFree(array $picked, ?int $exceptShipmentId = null): void
    {
        $busy = $this->busy($exceptShipmentId);
        $kinds = ['vehicle_id' => ['vehicles', 'Vehicle'], 'driver_id' => ['drivers', 'Driver'], 'helper_id' => ['helpers', 'Helper']];

        $errors = [];
        foreach ($picked as $field => [$id, $name]) {
            [$kind, $label] = $kinds[$field];
            if ($id && $shipment = $busy[$kind][(int) $id] ?? null) {
                $errors[$field] = "{$label} {$name} is still on shipment {$shipment->tracking_number} ({$shipment->statusLabel()}). "
                    .'It can be assigned again once that shipment is delivered, returned or cancelled.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * A shipment has just finished. Older shipments that were split across several trucks
     * have a leg per truck: close those and put trucks still marked "on the road" back to
     * Available, unless another open shipment has them.
     */
    public function release(Shipment $shipment): void
    {
        $legs = ShipmentVehicleAllocation::where('shipment_id', $shipment->shipment_id)->get();
        if ($legs->isEmpty()) {
            return;
        }

        $delivered = $shipment->status === 'delivered';
        ShipmentVehicleAllocation::whereIn('id', $legs->pluck('id'))->where('status', 'dispatched')->update([
            'status' => $delivered ? 'delivered' : 'cancelled',
            'delivered_at' => $delivered ? now() : null,
        ]);

        $stillOut = array_keys($this->busy($shipment->shipment_id)['vehicles']);
        Vehicle::whereIn('id', $legs->pluck('vehicle_id')->filter()->diff($stillOut))
            ->where('status', 'on_road')
            ->update(['status' => 'available']);
    }
}
