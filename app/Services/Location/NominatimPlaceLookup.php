<?php

namespace App\Services\Location;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Place search and reverse geocoding through OpenStreetMap Nominatim, asked from the server (identified, cached, never from the browser). */
class NominatimPlaceLookup implements PlaceLookup
{
    public function search(string $query, int $limit = 6): array
    {
        $query = trim($query);

        return Cache::remember('place:search:'.md5(strtolower($query)).':'.$limit, now()->addDay(), function () use ($query, $limit) {
            $rows = $this->get('search', [
                'q' => $query, 'format' => 'jsonv2', 'addressdetails' => 1, 'limit' => $limit,
                'countrycodes' => config('services.nominatim.country'),
            ]);

            return array_values(array_map([$this, 'place'], $rows));
        });
    }

    public function reverse(float $latitude, float $longitude): ?array
    {
        $key = 'place:reverse:'.round($latitude, 4).','.round($longitude, 4);

        return Cache::remember($key, now()->addDay(), function () use ($latitude, $longitude) {
            $row = $this->get('reverse', ['lat' => $latitude, 'lon' => $longitude, 'format' => 'jsonv2', 'addressdetails' => 1, 'zoom' => 18]);

            return isset($row['lat']) ? $this->place($row) : null;
        });
    }

    private function get(string $path, array $params): array
    {
        try {
            $response = Http::timeout(config('services.nominatim.timeout'))
                ->withHeaders(['User-Agent' => 'TakshAllInOne/1.0 ('.config('services.nominatim.contact').')'])
                ->get(rtrim(config('services.nominatim.base_url'), '/').'/'.$path, $params);
        } catch (\Throwable $e) {
            Log::warning('Place lookup unreachable', ['error' => $e->getMessage()]);
            throw new LookupUnavailable();
        }

        if ($response->failed()) {
            Log::warning('Place lookup failed', ['status' => $response->status()]);
            throw new LookupUnavailable();
        }

        return (array) $response->json();
    }

    /** One place in the shape the address form fills from. */
    private function place(array $row): array
    {
        $a = $row['address'] ?? [];
        $road = collect([$a['house_number'] ?? null, $a['road'] ?? null])->filter()->implode(' ');

        return [
            'display_name' => $row['display_name'] ?? '',
            'latitude' => (float) $row['lat'],
            'longitude' => (float) $row['lon'],
            'address' => [
                'line' => $road ?: ($a['neighbourhood'] ?? $a['suburb'] ?? ''),
                'area' => $a['suburb'] ?? $a['neighbourhood'] ?? $a['village'] ?? '',
                'city' => $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['state_district'] ?? $a['county'] ?? '',
                'state' => $a['state'] ?? '',
                'pincode' => isset($a['postcode']) ? preg_replace('/\D/', '', $a['postcode']) : '',
            ],
        ];
    }
}
