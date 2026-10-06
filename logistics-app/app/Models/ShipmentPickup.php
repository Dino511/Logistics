<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One pickup stop of a shipment that collects from several places. */
class ShipmentPickup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'picked_up_at' => 'datetime',
        ];
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id', 'shipment_id');
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'picked_up_by');
    }

    public function isCollected(): bool
    {
        return $this->picked_up_at !== null;
    }

    /** What to hand a maps app for directions: the pinned spot if there is one, else the address. */
    public function mapsQuery(): string
    {
        return $this->latitude !== null && $this->longitude !== null
            ? "{$this->latitude},{$this->longitude}"
            : collect([$this->address, $this->city, $this->province])->filter()->implode(', ');
    }
}
