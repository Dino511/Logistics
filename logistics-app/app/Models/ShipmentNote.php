<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentNote extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        // SQL Server returns ids as strings; cast so comparisons with user ids work.
        return ['user_id' => 'integer', 'shipment_id' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id', 'shipment_id');
    }
}
