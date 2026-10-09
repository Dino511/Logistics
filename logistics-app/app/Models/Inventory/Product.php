<?php

namespace App\Models\Inventory;

class Product extends InventoryModel
{
    protected $table = 'products';

    protected function casts(): array
    {
        return ['selling_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', 1);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function stock()
    {
        return $this->hasMany(Stock::class, 'product_id');
    }

    /** Total units across all locations. */
    public function getTotalStockAttribute(): int
    {
        return (int) $this->stock->sum('quantity');
    }
}
