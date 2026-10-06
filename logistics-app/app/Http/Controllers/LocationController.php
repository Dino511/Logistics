<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A small Philippine city/province/postal-code lookup for the shipment form's
 * autocomplete. This is a starter dataset (~65 major cities/municipalities), not the
 * full official PSGC list of 1,600+ cities and municipalities — see resources/data/
 * ph_locations.json. It exists purely to suggest and auto-fill; the form fields it
 * feeds are always plain free text, so anything not in this list can still be typed
 * in by hand and saves normally.
 */
class LocationController extends Controller
{
    private const MAX_RESULTS = 8;

    private const MAX_SUGGESTIONS = 4;

    public function search(Request $request)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'field' => ['required', 'in:city,province'],
            'province' => ['nullable', 'string', 'max:100'],
        ]);

        $q = Str::lower(trim($data['q']));
        $locations = $this->allLocations();

        $matches = $locations
            ->when($data['field'] === 'city' && ! empty($data['province']), fn ($rows) => $rows->filter(
                fn ($row) => Str::lower($row['province']) === Str::lower($data['province'])
            ))
            ->filter(function ($row) use ($data, $q) {
                $haystack = Str::lower($row[$data['field']]);

                return str_contains($haystack, $q);
            })
            // Matches starting with what was typed float to the top ("Ma" -> Makati before Mandaluyong-vs-Malabon order doesn't matter, but "Ma" before "...ma...").
            ->sortBy(fn ($row) => [! str_starts_with(Str::lower($row[$data['field']]), $q), $row[$data['field']]])
            ->values();

        // One row per distinct value for the field being searched (several cities can
        // share a province; the dropdown should list "Cavite" once, not six times).
        if ($data['field'] === 'province') {
            $matches = $matches->unique('province')->values();
        }

        return response()->json(
            $matches->take(self::MAX_RESULTS)->map(fn ($row) => [
                'city' => $row['city'],
                'province' => $row['province'],
                'postal_code' => $row['postal_code'],
            ])->all()
        );
    }

    /**
     * Places named in a free-text address, best match first, for the shipment form to
     * suggest or pre-fill the Location. "exact" marks the strongest matches found: when
     * there is only one of those, the form can safely fill it in without asking.
     */
    public function detect(Request $request)
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:255']]);
        $text = ' '.$this->plain($data['text']).' ';

        $matches = $this->allLocations()
            ->map(function ($row) use ($text) {
                $city = $this->plain($row['city']);
                // 3: the city by its full name. 2: without "City" ("batangas" for Batangas City).
                // 1: only its province is named, so it is one of several possibilities.
                $score = match (true) {
                    str_contains($text, " {$city} ") => 3,
                    str_ends_with($city, ' city') && str_contains($text, ' '.substr($city, 0, -5).' ') => 2,
                    str_contains($text, ' '.$this->plain($row['province']).' ') => 1,
                    default => 0,
                };

                return $row + ['score' => $score];
            })
            ->filter(fn ($row) => $row['score'] > 0)
            ->sortBy(fn ($row) => [-$row['score'], -strlen($row['city']), $row['city']])
            ->values();

        $best = $matches->max('score');

        return response()->json(
            $matches->take(self::MAX_SUGGESTIONS)->map(fn ($row) => [
                'city' => $row['city'],
                'province' => $row['province'],
                'postal_code' => $row['postal_code'],
                'exact' => $row['score'] >= 2 && $row['score'] === $best,
            ])->all()
        );
    }

    /** Lower-case, accents removed, punctuation turned into single spaces: "Las Piñas" => "las pinas". */
    private function plain(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($value))));
    }

    /** @return Collection<int, array{city:string, province:string, postal_code:string}> */
    private function allLocations(): Collection
    {
        // Cache the plain decoded array, not a Collection: unserializing an object from the
        // file cache driver can fail here with "incomplete object ... class not loaded before
        // unserialize()" depending on autoload timing, while a plain array never has that
        // problem. Wrap it in collect() fresh on every call instead.
        $rows = Cache::rememberForever('ph_locations_dataset', function () {
            $path = resource_path('data/ph_locations.json');

            return json_decode(file_get_contents($path), true) ?? [];
        });

        return collect($rows);
    }
}
