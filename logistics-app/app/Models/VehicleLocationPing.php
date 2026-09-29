<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/** One position from a driver's phone while they share their location during a delivery. */
class VehicleLocationPing extends Model
{
    use Prunable;

    /** How long positions are kept. They are personal data, so no longer than needed. */
    public const KEEP_DAYS = 90;

    /** A position older than this is shown as stale ("last seen …") rather than live. */
    public const LIVE_MINUTES = 5;

    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id', 'shipment_id');
    }

    /** Removed daily by the scheduled `model:prune` command (routes/console.php). */
    public function prunable(): Builder
    {
        return static::where('recorded_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    public function isLive(): bool
    {
        return $this->recorded_at->gt(now()->subMinutes(self::LIVE_MINUTES));
    }

    /** Shape used by the maps. */
    public function toMapPoint(): array
    {
        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'accuracy' => $this->accuracy_m,
            'recorded_at' => $this->recorded_at->toIso8601String(),
            'ago' => $this->recorded_at->diffForHumans(),
            'live' => $this->isLive(),
            'driver' => $this->driver?->name,
            'plate' => $this->vehicle?->plate_number,
            'phone' => $this->driver?->phone,
            'tel' => $this->driver?->phone ? 'tel:'.EmergencyContact::dialable($this->driver->phone) : null,
        ];
    }
}
