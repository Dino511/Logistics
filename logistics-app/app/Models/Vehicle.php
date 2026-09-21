<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    public const STATUSES = ['available', 'on_road', 'maintenance'];
    public const TYPES = ['Motorcycle', 'Van', 'Pickup', 'Truck', 'Trailer'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['capacity_kg' => 'decimal:2'];
    }

    public function drivers() { return $this->hasMany(Driver::class, 'vehicle_id'); }
    public function shipments() { return $this->hasMany(Shipment::class, 'vehicle_id'); }

    public static function statusLabel(string $s): string { return ucfirst(str_replace('_', ' ', $s)); }

    public static function badge(string $s): string
    {
        return ['available' => 'b-delivered', 'on_road' => 'b-transit', 'maintenance' => 'b-delayed'][$s] ?? 'b-pending';
    }
}
