<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Base for models that read the Inventory system's database.
 * Logistics must never write there, so writes are blocked here as well as by the DB login.
 */
abstract class InventoryModel extends Model
{
    protected $connection = 'inventory';

    public $timestamps = false;

    protected static function booted(): void
    {
        foreach (['creating', 'updating', 'deleting'] as $event) {
            static::$event(fn () => throw new LogicException('Inventory data is read-only from Logistics.'));
        }
    }
}
