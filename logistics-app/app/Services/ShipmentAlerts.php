<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Alert;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\ShipmentNote;
use App\Models\User;
use App\Models\VehicleLocationPing;
use Illuminate\Support\Str;

/**
 * Who hears about what happens to a shipment.
 *
 * "Involved" people are whoever created the shipment, the accounts of its drivers
 * (the main driver and any driver on a leg of a split shipment), and anyone who has
 * posted a note on it. The person who caused an event is never alerted about it.
 */
class ShipmentAlerts
{
    public function noteAdded(ShipmentNote $note): void
    {
        $shipment = $note->shipment;
        $this->send($this->involved($shipment), $shipment, 'note', "{$note->user->name} on {$shipment->tracking_number}: ".Str::limit($note->body, 120), $note->user_id);
    }

    /** Called for every status change; only some statuses are worth an alert. */
    public function statusChanged(Shipment $shipment, string $status, ?string $note, int $actorId): void
    {
        match ($status) {
            // A delay needs attention from managers too, not just the people on the shipment.
            'delayed' => $this->send(
                array_merge($this->involved($shipment), $this->activeUserIds(Role::Manager)),
                $shipment, 'delayed',
                "{$shipment->tracking_number} is delayed".($note ? ': '.Str::limit($note, 120) : '.'),
                $actorId
            ),
            'delivered' => $this->send($this->involved($shipment), $shipment, 'delivered', "{$shipment->tracking_number} was delivered to {$shipment->destination_name}.", $actorId),
            default => null,
        };
    }

    /** Tell drivers they have a shipment. $driverIds are drivers, not users. */
    public function driversAssigned(Shipment $shipment, array $driverIds, int $actorId): void
    {
        $userIds = Driver::whereIn('id', array_filter($driverIds))->whereNotNull('user_id')->pluck('user_id')->all();
        $this->send($userIds, $shipment, 'assigned', "New delivery for you: {$shipment->tracking_number} to {$shipment->destination_name}, {$shipment->destination_city}.", $actorId);
    }

    /** A driver's SOS: every Manager and Super Admin, plus whoever is involved in the delivery. */
    public function sos(User $from, ?Shipment $shipment, ?VehicleLocationPing $ping, ?string $message): void
    {
        $recipients = array_merge(
            $this->activeUserIds(Role::Manager),
            $this->activeUserIds(Role::SuperAdmin),
            $shipment ? $this->involved($shipment) : [],
        );

        $text = "🚨 SOS from {$from->name}"
            .($ping?->vehicle ? " ({$ping->vehicle->plate_number})" : '')
            .($shipment ? " on {$shipment->tracking_number}" : '')
            .($message ? ": {$message}" : '.')
            .($ping ? ' Last seen '.$ping->recorded_at->diffForHumans().'.' : ' No recent location.');

        $this->send($recipients, $shipment, 'sos', $text, $from->id);
    }

    /** @return int[] user ids */
    public function involved(Shipment $shipment): array
    {
        $driverIds = array_merge([$shipment->driver_id], $shipment->allocations()->pluck('driver_id')->all());

        return array_values(array_unique(array_filter(array_merge(
            [$shipment->created_by],
            Driver::whereIn('id', array_filter($driverIds))->whereNotNull('user_id')->pluck('user_id')->all(),
            ShipmentNote::where('shipment_id', $shipment->shipment_id)->distinct()->pluck('user_id')->all(),
        ))));
    }

    /** @return int[] */
    private function activeUserIds(Role $role): array
    {
        return User::where('role', $role->value)->where('is_active', true)->pluck('id')->all();
    }

    /** @param int[] $userIds */
    private function send(array $userIds, ?Shipment $shipment, string $type, string $message, int $exceptUserId): void
    {
        $recipients = User::whereIn('id', array_unique($userIds))
            ->where('id', '!=', $exceptUserId)
            ->where('is_active', true)
            ->pluck('id');

        $now = now();
        Alert::insert($recipients->map(fn ($id) => [
            'user_id' => $id,
            'shipment_id' => $shipment?->shipment_id,
            'type' => $type,
            'message' => Str::limit($message, 250),
            'created_at' => $now,
        ])->all());
    }
}
