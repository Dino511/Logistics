<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentStatusHistory extends Model
{
    protected $table = 'shipment_status_history';
    protected $primaryKey = 'history_id';
    public $timestamps = false;
    protected $guarded = ['history_id'];

    protected function casts(): array { return ['changed_at' => 'datetime']; }

    public function shipment() { return $this->belongsTo(Shipment::class, 'shipment_id'); }
}
