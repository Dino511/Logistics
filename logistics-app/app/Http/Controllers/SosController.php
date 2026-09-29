<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Shipment;
use App\Models\VehicleLocationPing;
use App\Services\ShipmentAlerts;
use App\Services\StockReservations;
use Illuminate\Http\Request;

/**
 * A driver's SOS: an urgent alert to the office with who, which delivery and where they
 * were last seen. It only notifies staff; the Emergency list tells drivers to call 911 first.
 */
class SosController extends Controller
{
    public function store(Request $request, ShipmentAlerts $alerts)
    {
        $user = $request->user();
        abort_unless($user->hasRole('field_personnel'), 403);
        $driver = $user->driver;

        $data = $request->validate([
            'shipment_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'string', 'max:300'],
        ]);

        // The delivery they're on: the one whose page they pressed SOS on, if it's theirs;
        // otherwise their most recent open delivery.
        $shipment = null;
        if ($driver) {
            $open = Shipment::assignedToDriver($driver->id)->whereIn('status', StockReservations::HOLDING_STATUSES);
            $shipment = (clone $open)->whereKey($data['shipment_id'] ?? 0)->first()
                ?? $open->orderByDesc('shipment_id')->first();
        }

        $ping = $driver
            ? VehicleLocationPing::with('vehicle')->where('driver_id', $driver->id)
                ->where('recorded_at', '>=', now()->subHours(12))->latest('recorded_at')->first()
            : null;

        $alerts->sos($user, $shipment, $ping, $data['message'] ?? null);

        $where = $ping ? " Last location {$ping->latitude},{$ping->longitude} ({$ping->recorded_at->diffForHumans()})." : ' No recent location.';
        if ($shipment) {
            $shipment->shipmentNotes()->create([
                'user_id' => $user->id,
                'body' => '🚨 SOS sent'.(! empty($data['message']) ? ': '.rtrim($data['message'], '.') : '').'.'.$where,
            ]);
        }
        ActivityLog::record('sos', "SOS from {$user->name}".($shipment ? " on {$shipment->tracking_number}" : '').'.'.$where, $shipment);

        return back()->with('warning', __('SOS sent. The office has been alerted. If anyone is in danger, call 911 now.'));
    }
}
