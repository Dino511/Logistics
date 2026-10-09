<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** How much of one shipment item (by quantity and weight) rode on one truck. */
class ShipmentAllocationItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['weight_kg' => 'decimal:2'];
    }

    public function allocation()
    {
        return $this->belongsTo(ShipmentVehicleAllocation::class, 'allocation_id');
    }

    public function shipmentItem()
    {
        return $this->belongsTo(ShipmentItem::class, 'shipment_item_id', 'shipment_item_id');
    }
}
