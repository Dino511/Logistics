<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A truck / cargo helper: rides with a vehicle and handles the load. */
class Helper extends Model
{
    protected $guarded = [];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /** The Field Personnel account this helper signs in with, if any. */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
