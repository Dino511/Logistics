<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Shipment extends Model
{
    /** Every status, in the order a shipment normally moves through them. */
    public const STATUSES = [
        'pending', 'ready_for_pickup',
        'picked_up', 'in_transit', 'out_for_delivery',
        'delivery_attempted', 'held_for_pickup', 'delayed',
        'delivered', 'returned', 'cancelled',
    ];

    /** The most places one shipment can collect from, the origin included. */
    public const MAX_PICKUPS = 5;

    /** Not finished yet: still to be delivered, and still holding its stock. */
    public const OPEN_STATUSES = ['pending', 'ready_for_pickup', 'picked_up', 'in_transit', 'out_for_delivery', 'delivery_attempted', 'held_for_pickup', 'delayed'];

    /** On a vehicle and moving: the driver's location can be shared and tracked. */
    public const ON_ROAD_STATUSES = ['picked_up', 'in_transit', 'out_for_delivery', 'delivery_attempted', 'delayed'];

    /** Something went wrong and the office should look at it. */
    public const PROBLEM_STATUSES = ['delayed', 'delivery_attempted'];

    /** Which statuses a shipment may move to from its current one. */
    private const TRANSITIONS = [
        'pending' => ['ready_for_pickup', 'picked_up', 'in_transit', 'cancelled'],
        'ready_for_pickup' => ['picked_up', 'in_transit', 'cancelled'],
        'picked_up' => ['in_transit', 'out_for_delivery', 'delayed', 'cancelled'],
        'in_transit' => ['out_for_delivery', 'delivered', 'delayed', 'cancelled'],
        'out_for_delivery' => ['delivered', 'delivery_attempted', 'delayed', 'cancelled'],
        'delivery_attempted' => ['out_for_delivery', 'held_for_pickup', 'delivered', 'returned', 'cancelled'],
        'held_for_pickup' => ['delivered', 'out_for_delivery', 'returned', 'cancelled'],
        'delayed' => ['in_transit', 'out_for_delivery', 'delivered', 'returned', 'cancelled'],
        'delivered' => [],
        'returned' => [],
        'cancelled' => [],
    ];

    /** Statuses Field Personnel may never set: the office decides these. */
    private const OFFICE_ONLY_STATUSES = ['returned', 'cancelled'];

    private const LABELS = [
        'returned' => 'Returned to sender',
    ];

    /** What each status means, shown to drivers on their dashboard and when they pick one. */
    private const DESCRIPTIONS = [
        'pending' => 'The order has been received and the items are being prepared for packing.',
        'ready_for_pickup' => 'The shipment is packed and labelled, waiting for the driver to collect it.',
        'picked_up' => 'The driver has collected the shipment and it is loaded on the vehicle.',
        'in_transit' => 'The shipment is on the road, moving towards its destination.',
        'out_for_delivery' => 'The shipment is on the delivery vehicle and is due to arrive today.',
        'delivery_attempted' => 'The driver tried to deliver but could not: access was blocked, a signature was missing, or no one was there.',
        'held_for_pickup' => 'The shipment is waiting at a pickup point for the receiver to collect it.',
        'delayed' => 'Something is holding the shipment up, such as bad weather, an address problem or a vehicle or route issue.',
        'delivered' => 'The shipment has been left at the delivery location or handed to the receiver.',
        'returned' => 'Delivery failed or the address was wrong, so the shipment is going back to where it came from.',
        'cancelled' => 'The shipment was called off and will not be delivered.',
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

    /**
     * Pickup stops in visiting order, for shipments that collect from several places.
     * Empty when there is a single pickup (the origin).
     */
    public function pickups()
    {
        return $this->hasMany(ShipmentPickup::class, 'shipment_id', 'shipment_id')->orderBy('sequence');
    }

    /** The next stop still to be collected, if any. */
    public function nextPickup(): ?ShipmentPickup
    {
        return $this->pickups->first(fn ($p) => ! $p->isCollected());
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
        return self::LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function description(string $status): string
    {
        return self::DESCRIPTIONS[$status] ?? '';
    }

    public static function badge(string $status): string
    {
        return [
            'pending' => 'b-pending',
            'ready_for_pickup' => 'b-pending',
            'picked_up' => 'b-transit',
            'in_transit' => 'b-transit',
            'out_for_delivery' => 'b-transit',
            'delivery_attempted' => 'b-delayed',
            'held_for_pickup' => 'b-pending',
            'delayed' => 'b-delayed',
            'delivered' => 'b-delivered',
            'returned' => 'b-inactive',
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
     * What Field Personnel may change this shipment to. Before it's collected the only
     * thing they can do is pick it up; after that, anything except Returned to sender or
     * Cancelled (office staff decide those).
     */
    public function fieldNextStatuses(): array
    {
        if ($this->status === 'ready_for_pickup') {
            return ['picked_up'];
        }
        if ($this->status === 'pending') {
            return [];
        }

        return array_values(array_diff($this->allowedNextStatuses(), self::OFFICE_ONLY_STATUSES));
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
