<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentItem;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Stock promised to shipments that haven't finished yet.
 *
 * Logistics can't write to the Inventory database, so Inventory's quantity still
 * counts goods already assigned to a Pending, In transit or Delayed shipment. What is
 * really free to ship is Inventory's quantity minus that reserved amount. Nothing is
 * stored separately: reservations are totalled from shipment_items, so they can't
 * drift, and a shipment releases its stock the moment it's Delivered, Returned or Cancelled.
 */
class StockReservations
{
    /** Shipment statuses that still hold on to their stock. */
    public const HOLDING_STATUSES = Shipment::OPEN_STATUSES;

    /**
     * Reserved quantity per "productId:locationId".
     *
     * @return array<string, int>
     */
    public function reserved(): array
    {
        return ShipmentItem::query()
            ->join('shipments', 'shipments.shipment_id', '=', 'shipment_items.shipment_id')
            ->whereIn('shipments.status', self::HOLDING_STATUSES)
            ->groupBy('shipment_items.inventory_product_id', 'shipment_items.inventory_location_id')
            ->selectRaw('shipment_items.inventory_product_id as product_id, shipment_items.inventory_location_id as location_id, SUM(shipment_items.quantity) as reserved')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->product_id}:{$row->location_id}" => (int) $row->reserved])
            ->all();
    }

    /** What can still be shipped, never below zero. */
    public function available(int $onHand, int $reserved): int
    {
        return max(0, $onHand - $reserved);
    }

    /**
     * Run $callback while no other request can reserve stock, so two shipments
     * created at the same moment can't both take the last units.
     *
     * @throws LockTimeoutException if the lock can't be had within 10 seconds
     */
    public function exclusively(Closure $callback): mixed
    {
        return Cache::lock('stock-reservations', 30)->block(10, $callback);
    }
}
