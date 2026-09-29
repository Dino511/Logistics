<?php

namespace App\Models\Inventory;

class StockTransfer extends InventoryModel
{
    protected $table = 'inventory_transfers';

    public function destination()
    {
        return $this->belongsTo(Stock::class, 'destination_inventory_id');
    }

    /** Transfers here complete immediately, so none are ever pending. */
    public function scopePending($q)
    {
        return $q->whereRaw('1 = 0');
    }
}
