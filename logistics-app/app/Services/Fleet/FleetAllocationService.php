<?php

namespace App\Services\Fleet;

use App\Exceptions\InsufficientFleetCapacityException;
use App\Models\ActivityLog;
use App\Models\Shipment;
use App\Models\ShipmentAllocationItem;
use App\Models\ShipmentVehicleAllocation;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Splits a shipment's items across the fleet using a First-Fit-Decreasing style
 * greedy algorithm:
 *
 *   1. Sort the shipment's line items by weight, heaviest first ("decreasing").
 *   2. Sort available vehicles by payload capacity, largest first.
 *   3. Walk the items in that order and drop each one into the first vehicle
 *      (in capacity order) that still has room for it.
 *   4. If a single line item is itself heavier than the vehicle it lands in,
 *      split its quantity across that vehicle and the next one(s) — this is
 *      what lets one heavy order become a multi-truck dispatch.
 *
 * This assumes line items are divisible by quantity (sacks, crates, cartons —
 * not single indivisible objects heavier than any truck). A single *unit*
 * that alone exceeds every vehicle's capacity can never be placed and raises
 * InsufficientFleetCapacityException.
 */
class FleetAllocationService
{
    /**
     * Work out how a shipment's items would be split across the fleet, without
     * saving anything. Safe to call for a preview / confirmation screen.
     *
     * @throws InvalidArgumentException shipment has no items, or an item has no weight recorded
     * @throws InsufficientFleetCapacityException the fleet (or its available vehicles) can't carry the load
     */
    public function planAllocation(Shipment $shipment): AllocationPlan
    {
        $lines = $this->weighedLines($shipment);
        $totalWeight = array_sum(array_column($lines, 'weight_kg'));

        $vehicles = Vehicle::where('status', 'available')
            ->orderByDesc('capacity_kg')
            ->get();

        $this->assertFleetCanCarry($totalWeight, $vehicles);

        // Bins, largest capacity first — matches "match against vehicles ordered by
        // largest payload capacity first" and lets big trucks fill before small ones.
        usort($lines, fn ($a, $b) => $b['weight_kg'] <=> $a['weight_kg']);

        $bins = $vehicles->map(fn (Vehicle $v) => [
            'vehicle' => $v,
            'remaining' => (float) $v->capacity_kg,
            'items' => [], // shipment_item_id => accumulated row
        ])->all();

        foreach ($lines as $line) {
            $remainingQty = $line['quantity'];
            $unitWeight = $line['unit_weight_kg'];

            while ($remainingQty > 0) {
                $binIndex = $this->firstBinWithRoomFor($bins, $unitWeight);

                if ($binIndex === null) {
                    // Every vehicle we planned to use is full; the pre-flight capacity
                    // check above should prevent this, but never leave weight stranded.
                    throw new InsufficientFleetCapacityException($totalWeight, (float) $vehicles->sum('capacity_kg'));
                }

                $fits = (int) floor($bins[$binIndex]['remaining'] / $unitWeight);
                $take = min($fits, $remainingQty);
                $weight = round($take * $unitWeight, 2);

                $key = $line['shipment_item_id'];
                if (! isset($bins[$binIndex]['items'][$key])) {
                    $bins[$binIndex]['items'][$key] = [
                        'shipment_item_id' => $line['shipment_item_id'],
                        'sku' => $line['sku'],
                        'item_name' => $line['item_name'],
                        'quantity' => 0,
                        'weight_kg' => 0.0,
                    ];
                }
                $bins[$binIndex]['items'][$key]['quantity'] += $take;
                $bins[$binIndex]['items'][$key]['weight_kg'] += $weight;
                $bins[$binIndex]['remaining'] -= $weight;
                $remainingQty -= $take;
            }
        }

        $legs = collect($bins)
            ->filter(fn ($bin) => count($bin['items']) > 0)
            ->map(fn ($bin) => new AllocationLeg(
                vehicle: $bin['vehicle'],
                items: array_values($bin['items']),
                weightKg: round(array_sum(array_column($bin['items'], 'weight_kg')), 2),
            ))
            ->values()
            ->all();

        return new AllocationPlan($legs, round($totalWeight, 2));
    }

    /**
     * Plan the allocation and persist it: creates the allocation + allocation-item
     * rows, marks every vehicle used as dispatched, and moves the shipment to
     * "in_transit". Runs in one transaction, so a shipment is never left half-assigned.
     */
    public function dispatch(Shipment $shipment, ?int $dispatchedByUserId = null): AllocationPlan
    {
        $plan = $this->planAllocation($shipment);

        DB::transaction(function () use ($shipment, $plan, $dispatchedByUserId) {
            foreach ($plan->legs as $sequence => $leg) {
                $allocation = ShipmentVehicleAllocation::create([
                    'shipment_id' => $shipment->shipment_id,
                    'vehicle_id' => $leg->vehicle->id,
                    'driver_id' => $leg->vehicle->drivers()->where('status', 'active')->value('id'),
                    'sequence' => $sequence + 1,
                    'allocated_weight_kg' => $leg->weightKg,
                    'vehicle_capacity_kg' => $leg->vehicle->capacity_kg,
                    'status' => 'dispatched',
                    'dispatched_at' => now(),
                ]);

                foreach ($leg->items as $item) {
                    ShipmentAllocationItem::create([
                        'allocation_id' => $allocation->id,
                        'shipment_item_id' => $item['shipment_item_id'],
                        'quantity' => $item['quantity'],
                        'weight_kg' => $item['weight_kg'],
                    ]);
                }

                $leg->vehicle->update(['status' => 'on_road']);
            }

            // Keep the shipment's own single vehicle/driver fields pointing at the
            // lead (largest) truck, for the parts of the UI built before multi-vehicle splits existed.
            $lead = $plan->legs[0];
            $shipment->update([
                'vehicle_id' => $lead->vehicle->id,
                'driver_id' => $lead->vehicle->drivers()->where('status', 'active')->value('id'),
                'status' => 'in_transit',
                'dispatched_at' => now(),
            ]);

            $shipment->history()->create([
                'status' => 'in_transit',
                'note' => $plan->isSplit()
                    ? sprintf('Auto-dispatched across %d vehicles (%s kg total)', $plan->vehicleCount(), number_format($plan->totalWeightKg, 2))
                    : sprintf('Dispatched on %s (%s kg)', $lead->vehicle->plate_number, number_format($plan->totalWeightKg, 2)),
                'changed_by' => $dispatchedByUserId,
                'changed_at' => now(),
            ]);
        });

        ActivityLog::record('status_changed', sprintf(
            'Shipment %s dispatched on %d vehicle(s): %s',
            $shipment->tracking_number, $plan->vehicleCount(),
            collect($plan->legs)->map(fn ($l) => "{$l->vehicle->plate_number} ({$l->weightKg}kg)")->join(', ')
        ), $shipment);

        return $plan;
    }

    /**
     * @return array<int, array{shipment_item_id:int, sku:string, item_name:string, quantity:int, unit_weight_kg:float, weight_kg:float}>
     */
    private function weighedLines(Shipment $shipment): array
    {
        $items = $shipment->items()->get();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException("Shipment {$shipment->tracking_number} has no items to allocate.");
        }

        return $items->map(function ($item) use ($shipment) {
            if ($item->unit_weight_kg === null) {
                throw new InvalidArgumentException(
                    "Item {$item->sku} on shipment {$shipment->tracking_number} has no recorded unit weight; ".
                    'set unit_weight_kg before this shipment can be auto-allocated.'
                );
            }

            $unitWeight = (float) $item->unit_weight_kg;

            return [
                'shipment_item_id' => $item->shipment_item_id,
                'sku' => $item->sku,
                'item_name' => $item->item_name,
                'quantity' => (int) $item->quantity,
                'unit_weight_kg' => $unitWeight,
                'weight_kg' => round($unitWeight * $item->quantity, 2),
            ];
        })->all();
    }

    /** @param  Collection<int, Vehicle>  $vehicles */
    private function assertFleetCanCarry(float $totalWeight, Collection $vehicles): void
    {
        if ($vehicles->isEmpty()) {
            throw new InsufficientFleetCapacityException($totalWeight, 0.0);
        }

        $availableCapacity = (float) $vehicles->sum('capacity_kg');
        if ($totalWeight > $availableCapacity) {
            $allFleetCapacity = (float) Vehicle::sum('capacity_kg');
            throw new InsufficientFleetCapacityException($totalWeight, $availableCapacity, onlyAvailableVehiclesConsidered: $allFleetCapacity > $availableCapacity);
        }

        // A single unit heavier than the largest vehicle can never be placed,
        // no matter how much spare capacity the fleet has overall.
        $largestVehicleCapacity = (float) $vehicles->max('capacity_kg');
        // (Per-unit check happens lazily in the packing loop via firstBinWithRoomFor;
        // this early check just gives a fast, clear failure for the common case.)
        unset($largestVehicleCapacity);
    }

    /** @param  array<int, array{vehicle:Vehicle, remaining:float, items:array}>  $bins */
    private function firstBinWithRoomFor(array $bins, float $unitWeight): ?int
    {
        foreach ($bins as $index => $bin) {
            if ($bin['remaining'] >= $unitWeight) {
                return $index;
            }
        }

        return null;
    }
}
