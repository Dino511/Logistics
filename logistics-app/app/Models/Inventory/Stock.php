<?php

namespace App\Models\Inventory;

/** One row per product per location (inventories table). */
class Stock extends InventoryModel
{
    protected $table = 'inventories';

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /** Products at or below their reorder point at a given location. */
    public function scopeLow($q)
    {
        return $q->join('products', 'products.id', '=', 'inventories.product_id')
            ->where('products.is_active', 1)
            ->whereColumn('inventories.quantity', '<=', 'products.reorder_point')
            ->select('inventories.*');
    }
}
