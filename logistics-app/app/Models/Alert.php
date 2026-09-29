<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One item under a user's bell. See App\Services\ShipmentAlerts for who gets what. */
class Alert extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        // SQL Server returns ids as strings; cast so strict comparisons with user ids work.
        return ['user_id' => 'integer', 'shipment_id' => 'integer', 'read_at' => 'datetime'];
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id', 'shipment_id');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
