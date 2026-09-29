<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Turns a Philippine address into map coordinates using OpenStreetMap's free
 * Nominatim service. Results are cached, and failures just return null so a
 * missing map never blocks creating or viewing a shipment.
 */
class Geocoder
{
    /** @return array{0: float, 1: float}|null [latitude, longitude] */
    public function locate(?string $address, ?string $city, ?string $province): ?array
    {
        // Try the full address first, then fall back to just the city.
        $attempts = array_unique(array_filter([
            collect([$address, $city, $province])->filter()->join(', '),
            collect([$city, $province])->filter()->join(', '),
        ]));

        foreach ($attempts as $query) {
            $key = 'geocode:'.md5($query);

            // "false" is cached too, so an unknown address isn't looked up on every page view.
            if (($point = Cache::get($key)) === null) {
                try {
                    $results = Http::timeout(5)
                        ->withHeaders(['User-Agent' => config('app.name', 'Logistics').' route map'])
                        ->get('https://nominatim.openstreetmap.org/search', [
                            'q' => $query,
                            'countrycodes' => 'ph',
                            'format' => 'json',
                            'limit' => 1,
                        ])
                        ->throw()
                        ->json();

                    $point = isset($results[0]['lat']) ? [(float) $results[0]['lat'], (float) $results[0]['lon']] : false;
                    Cache::put($key, $point, now()->addDays(30));
                } catch (\Throwable $e) {
                    // Offline or service busy: stop trying for a while, then try again later.
                    report($e);
                    Cache::put($key, false, now()->addMinutes(30));

                    return null;
                }
            }

            if ($point) {
                return $point;
            }
        }

        return null;
    }

    /** Fill in whichever end of the shipment has no coordinates yet. Returns true if anything changed. */
    public function fillShipment(Shipment $shipment): bool
    {
        $changed = false;

        foreach (['origin', 'destination'] as $end) {
            if ($shipment->{"{$end}_latitude"} !== null && $shipment->{"{$end}_longitude"} !== null) {
                continue;
            }

            $point = $this->locate($shipment->{"{$end}_address"}, $shipment->{"{$end}_city"}, $shipment->{"{$end}_province"});
            if ($point) {
                [$shipment->{"{$end}_latitude"}, $shipment->{"{$end}_longitude"}] = $point;
                $changed = true;
            }
        }

        return $changed;
    }
}
