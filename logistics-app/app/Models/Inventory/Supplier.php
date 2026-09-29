<?php

namespace App\Models\Inventory;

class Supplier extends InventoryModel
{
    protected $table = 'suppliers';

    public function scopeActive($q)
    {
        return $q->where('is_active', 1);
    }
}
