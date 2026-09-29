<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    public const STATUSES = ['available', 'on_road', 'maintenance'];

    /** Cargo vehicle models commonly used for Philippine road logistics, light to heavy duty. */
    public const TYPES = [
        // Light vehicles (car-derived vans / pickups)
        'Suzuki Carry (Dropside)',
        'Toyota Lite Ace (Dropside)',
        'Hyundai H-100 (Cab & Chassis)',
        'Mitsubishi L300 (FB/Dropside)',
        'Kia K2500 (Dropside/Karga)',
        // Light-duty trucks (4-wheeler to 6-wheeler)
        'Isuzu QKR (4-Wheeler)',
        'Fuso Canter / Isuzu NLR-NPR (6-Wheeler)',
        'Foton Tornado (Light Duty)',
        'Hino 300 Series',
        // Medium- and heavy-duty trucks
        'Isuzu F-Series (FRR/FSR)',
        'Isuzu FVZ/FVM (32ft)',
        'Hino 500 Series',
        'Chinese Heavy Truck (HOWO/Shacman/Foton EST)',
    ];

    /**
     * A reasonable starting payload (kg) for each type, taken from the midpoint of its
     * typical range. Vehicles vary by trim, so this is only a suggestion the Fleet form
     * pre-fills — the actual capacity_kg on each vehicle is what allocation always uses.
     */
    public const TYPICAL_PAYLOAD_KG = [
        'Suzuki Carry (Dropside)' => 940,
        'Toyota Lite Ace (Dropside)' => 825,
        'Hyundai H-100 (Cab & Chassis)' => 1100,
        'Mitsubishi L300 (FB/Dropside)' => 1215,
        'Kia K2500 (Dropside/Karga)' => 1400,
        'Isuzu QKR (4-Wheeler)' => 2240,
        'Fuso Canter / Isuzu NLR-NPR (6-Wheeler)' => 3750,
        'Foton Tornado (Light Duty)' => 3750,
        'Hino 300 Series' => 3900,
        'Isuzu F-Series (FRR/FSR)' => 8250,
        'Isuzu FVZ/FVM (32ft)' => 18995,
        'Hino 500 Series' => 14000,
        'Chinese Heavy Truck (HOWO/Shacman/Foton EST)' => 20000,
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['capacity_kg' => 'decimal:2', 'is_rented' => 'boolean'];
    }

    public function drivers()
    {
        return $this->hasMany(Driver::class, 'vehicle_id');
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class, 'vehicle_id');
    }

    public function allocations()
    {
        return $this->hasMany(ShipmentVehicleAllocation::class);
    }

    public function lease()
    {
        return $this->hasOne(VehicleLease::class);
    }

    public static function statusLabel(string $s): string
    {
        return ucfirst(str_replace('_', ' ', $s));
    }

    public static function badge(string $s): string
    {
        return ['available' => 'b-delivered', 'on_road' => 'b-transit', 'maintenance' => 'b-delayed'][$s] ?? 'b-pending';
    }
}
