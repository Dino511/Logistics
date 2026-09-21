<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    public const STATUSES = ['active', 'on_leave', 'inactive'];

    protected $guarded = [];

    public function vehicle() { return $this->belongsTo(Vehicle::class, 'vehicle_id'); }
    public function shipments() { return $this->hasMany(Shipment::class, 'driver_id'); }

    public static function statusLabel(string $s): string { return ucfirst(str_replace('_', ' ', $s)); }

    public static function badge(string $s): string
    {
        return ['active' => 'b-delivered', 'on_leave' => 'b-pending', 'inactive' => 'b-inactive'][$s] ?? 'b-pending';
    }
}
