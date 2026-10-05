<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Shipment;
use App\Models\VehicleLocationPing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Live vehicle tracking from the driver's phone. The driver opts in on the shipment page;
 * while that page is open the browser sends its position about once a minute. Positions
 * are accepted only for the driver's own shipments that are In transit or Delayed.
 */
class TrackingController extends Controller
{
    /** Shipment statuses during which a vehicle may be tracked. */
    public const TRACKABLE_STATUSES = Shipment::ON_ROAD_STATUSES;

    /** Ignore positions sent closer together than this, per driver. */
    private const MIN_SECONDS_BETWEEN_PINGS = 20;

    /** The live map shows vehicles seen within this many hours. */
    private const MAP_WINDOW_HOURS = 12;

    /** Driver pressed Start or Stop sharing. Logged, so there's a record of consent. */
    public function sharing(Request $request, Shipment $shipment): JsonResponse
    {
        $driver = $this->authorizedDriver($request, $shipment);
        $data = $request->validate(['action' => ['required', Rule::in(['start', 'stop'])]]);

        ActivityLog::record(
            $data['action'] === 'start' ? 'tracking_started' : 'tracking_stopped',
            ($data['action'] === 'start' ? 'Started' : 'Stopped')." sharing location for shipment {$shipment->tracking_number} (driver {$driver->name})",
            $shipment
        );

        return response()->json(['ok' => true]);
    }

    /** One position from the driver's phone. */
    public function ping(Request $request, Shipment $shipment): JsonResponse
    {
        $driver = $this->authorizedDriver($request, $shipment);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $last = VehicleLocationPing::where('driver_id', $driver->id)->latest('recorded_at')->first();
        if ($last && $last->recorded_at->gt(now()->subSeconds(self::MIN_SECONDS_BETWEEN_PINGS))) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        // The truck this driver is on for this shipment: their leg of a split shipment,
        // else the shipment's vehicle, else the driver's usual vehicle.
        $vehicleId = $shipment->allocations()->where('driver_id', $driver->id)->value('vehicle_id')
            ?? $shipment->vehicle_id
            ?? $driver->vehicle_id;

        $ping = VehicleLocationPing::create([
            'driver_id' => $driver->id,
            'shipment_id' => $shipment->shipment_id,
            'vehicle_id' => $vehicleId,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy_m' => isset($data['accuracy']) ? (int) round($data['accuracy']) : null,
            'recorded_at' => now(),
        ]);

        return response()->json(['ok' => true, 'recorded_at' => $ping->recorded_at->toIso8601String()]);
    }

    /** Office: all vehicles currently sharing, on one map. */
    public function index()
    {
        return view('tracking.index', ['positions' => $this->latestPositions()]);
    }

    /** Office: the same data as JSON, for the map's automatic refresh. */
    public function positions(): JsonResponse
    {
        return response()->json($this->latestPositions());
    }

    /**
     * Latest position of each driver on each active shipment, seen within the map window.
     * Per driver, not per shipment, so every truck of a split shipment gets its own marker.
     */
    public static function latestPingIds(?int $shipmentId = null)
    {
        return VehicleLocationPing::query()
            ->where('recorded_at', '>=', now()->subHours(self::MAP_WINDOW_HOURS))
            ->when($shipmentId, fn ($q) => $q->where('shipment_id', $shipmentId))
            ->groupBy('shipment_id', 'driver_id')
            ->selectRaw('MAX(id)');
    }

    private function latestPositions(): array
    {
        return VehicleLocationPing::query()
            ->whereIn('id', self::latestPingIds())
            ->whereHas('shipment', fn ($q) => $q->whereIn('status', self::TRACKABLE_STATUSES))
            ->with(['driver', 'vehicle', 'shipment'])
            ->orderByDesc('recorded_at')
            ->get()
            ->map(fn (VehicleLocationPing $p) => $p->toMapPoint() + [
                'tracking_number' => $p->shipment->tracking_number,
                'destination' => $p->shipment->destination_name.', '.$p->shipment->destination_city,
                'status' => $p->shipment->statusLabel(),
                'url' => route('shipments.show', $p->shipment),
            ])
            ->all();
    }

    /**
     * The signed-in Field Personnel's driver, if this shipment is theirs and on the road.
     * Otherwise 403, or 409 once the shipment is no longer trackable (the phone stops sending).
     */
    private function authorizedDriver(Request $request, Shipment $shipment)
    {
        $user = $request->user();
        $driver = $user->driver;
        abort_unless($user->hasRole('field_personnel') && $shipment->isAssignedToDriver($driver), 403);
        abort_unless(in_array($shipment->status, self::TRACKABLE_STATUSES, true), 409, 'This shipment is no longer on the road.');

        return $driver;
    }
}
