<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;

/**
 * The standing reminder shown on every page while deliveries are still unfinished.
 * Nothing is stored: it is worked out from the shipments themselves, so it disappears by
 * itself the moment the last one is Delivered, Returned or Cancelled.
 */
class OpenShipments
{
    /**
     * Office staff are reminded of every unfinished shipment; Field Personnel only of
     * the ones assigned to them.
     *
     * @return array{count:int, overdue:int, first:Shipment, url:string}|null
     */
    public function reminderFor(User $user): ?array
    {
        try {
            $query = Shipment::whereIn('status', Shipment::OPEN_STATUSES);
            if ($user->hasRole('field_personnel')) {
                if (! $user->driver) {
                    return null;
                }
                $query->assignedToDriver($user->driver->id);
            }

            $count = (clone $query)->count();
            if ($count === 0) {
                return null;
            }
            $first = (clone $query)->orderBy('scheduled_delivery_at')->first();

            return [
                'count' => $count,
                'overdue' => (clone $query)->where('scheduled_delivery_at', '<', now())->count(),
                'first' => $first,
                'url' => $count === 1 ? route('shipments.show', $first) : route('shipments.index', ['status' => 'open']),
            ];
        } catch (\Throwable $e) {
            // A reminder must never take a page down with it.
            return null;
        }
    }
}
