<?php

namespace App\Models\Inventory;

class StockTransfer extends InventoryModel
{
    protected $table = 'stock_transfers';

    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }
}
