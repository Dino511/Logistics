<?php

namespace App\Models;

use App\Models\Inventory\Location;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;

class ShipmentItem extends Model
{
    protected $primaryKey = 'shipment_item_id';

    public $timestamps = false;

    protected $guarded = ['shipment_item_id'];

    public function shipment() { return $this->belongsTo(Shipment::class, 'shipment_id'); }

    // Cross-database relations: the related tables live in the Inventory DB.
    public function product() { return $this->belongsTo(Product::class, 'inventory_product_id'); }
    public function fromLocation() { return $this->belongsTo(Location::class, 'inventory_location_id'); }
}
