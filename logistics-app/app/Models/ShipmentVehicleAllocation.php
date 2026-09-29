<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One truck's leg of a shipment. A normal shipment has exactly one of these;
 * a heavy order that got split across trucks has one row per truck (same
 * shipment_id, increasing `sequence`).
 */
class ShipmentVehicleAllocation extends Model
{
    public const STATUSES = ['pending', 'dispatched', 'delivered', 'cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'allocated_weight_kg' => 'decimal:2',
            'vehicle_capacity_kg' => 'decimal:2',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id', 'shipment_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function items()
    {
        return $this->hasMany(ShipmentAllocationItem::class, 'allocation_id');
    }

    public function loadPercent(): float
    {
        return $this->vehicle_capacity_kg > 0
            ? round(($this->allocated_weight_kg / $this->vehicle_capacity_kg) * 100, 1)
            : 0.0;
    }
}
