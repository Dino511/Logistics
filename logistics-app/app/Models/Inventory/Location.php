<?php

namespace App\Models\Inventory;

class Location extends InventoryModel
{
    protected $table = 'locations';

    public function scopeActive($q)
    {
        return $q->where('is_active', 1);
    }

    public function scopeWarehouses($q)
    {
        return $q->where('type', 'warehouse');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
}
