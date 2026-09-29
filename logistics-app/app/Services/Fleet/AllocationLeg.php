<?php

namespace App\Services\Fleet;

use App\Models\Vehicle;

/** One truck's share of a plan: the vehicle, its load, and which items (by shipment_item_id) it carries. */
final class AllocationLeg
{
    /** @param  array<int, array{shipment_item_id:int, sku:string, item_name:string, quantity:int, weight_kg:float}>  $items */
    public function __construct(
        public readonly Vehicle $vehicle,
        public readonly array $items,
        public readonly float $weightKg,
    ) {}

    public function loadPercent(): float
    {
        $capacity = (float) $this->vehicle->capacity_kg;

        return $capacity > 0 ? round(($this->weightKg / $capacity) * 100, 1) : 0.0;
    }
}
