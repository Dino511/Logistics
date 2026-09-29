<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    public function items()
    {
        return $this->hasMany(ShipmentItem::class, 'shipment_id');
    }

    public function history()
    {
        return $this->hasMany(ShipmentStatusHistory::class, 'shipment_id')->latest('changed_at');
    }

    /** The truck(s) this shipment was split across, in dispatch order. */
    public function allocations()
    {
        return $this->hasMany(ShipmentVehicleAllocation::class, 'shipment_id', 'shipment_id')->orderBy('sequence');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

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

    public function statusLabel(): string
    {
        return self::label($this->status);
    }

    public function badgeClass(): string
    {
        return self::badge($this->status);
    }

    public function allowedNextStatuses(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    /**
     * What Field Personnel may change this shipment to: only once it's on the road, and
     * never Cancelled (office staff decide that).
     */
    public function fieldNextStatuses(): array
    {
        if (! in_array($this->status, ['in_transit', 'delayed'], true)) {
            return [];
        }

        return array_values(array_intersect($this->allowedNextStatuses(), ['in_transit', 'delivered', 'delayed']));
    }

    /** Shipments carried by a driver: as the main driver, or on one of its vehicle legs. */
    public function scopeAssignedToDriver($query, int $driverId)
    {
        return $query->where(fn ($q) => $q->where('driver_id', $driverId)
            ->orWhereHas('allocations', fn ($a) => $a->where('driver_id', $driverId)));
    }

    public function isAssignedToDriver(?Driver $driver): bool
    {
        return $driver !== null && static::whereKey($this->getKey())->assignedToDriver($driver->id)->exists();
    }

    /** The notes thread. Not `notes`: that name is taken by the shipment's own notes column. */
    public function shipmentNotes()
    {
        return $this->hasMany(ShipmentNote::class, 'shipment_id', 'shipment_id')->orderBy('id');
    }

    public function proofPhotoUrl(): ?string
    {
        return $this->proof_photo_path ? Storage::disk('public')->url($this->proof_photo_path) : null;
    }
}
