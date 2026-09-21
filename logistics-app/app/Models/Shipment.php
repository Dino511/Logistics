<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    public const STATUSES = ['pending', 'in_transit', 'delivered', 'delayed', 'cancelled'];

    /** Which statuses a shipment may move to from its current one. */
    private const TRANSITIONS = [
        'pending' => ['in_transit', 'cancelled'],
        'in_transit' => ['delivered', 'delayed', 'cancelled'],
        'delayed' => ['in_transit', 'delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    protected $primaryKey = 'shipment_id';

    protected $guarded = ['shipment_id', 'delivery_result'];

    protected function casts(): array
    {
        return [
            'scheduled_pickup_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'scheduled_delivery_at' => 'datetime',
            'actual_delivery_at' => 'datetime',
        ];
    }

    public function items() { return $this->hasMany(ShipmentItem::class, 'shipment_id'); }
    public function history() { return $this->hasMany(ShipmentStatusHistory::class, 'shipment_id')->latest('changed_at'); }
    public function driver() { return $this->belongsTo(Driver::class, 'driver_id'); }
    public function vehicle() { return $this->belongsTo(Vehicle::class, 'vehicle_id'); }
    public function customer() { return $this->belongsTo(Customer::class, 'customer_id'); }

    public static function label(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }

    public static function badge(string $status): string
    {
        return [
            'pending' => 'b-pending',
            'in_transit' => 'b-transit',
            'delivered' => 'b-delivered',
            'delayed' => 'b-delayed',
            'cancelled' => 'b-inactive',
        ][$status] ?? 'b-pending';
    }

    public function statusLabel(): string { return self::label($this->status); }
    public function badgeClass(): string { return self::badge($this->status); }

    public function allowedNextStatuses(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }
}
