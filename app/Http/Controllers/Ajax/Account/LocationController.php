<?php

namespace App\Http\Controllers\Ajax\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CoordinatesRequest;
use App\Http\Requests\Account\PlaceSearchRequest;
use App\Services\Location\PlaceLookup;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

/** The map's place search and reverse lookup (the paths the Taksh app lists: /location/search, /geocode, /reverse-geocode). */
class LocationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly PlaceLookup $places) {}

    /** GET /location/search?q= - suggestions for a typed place or address */
    public function search(PlaceSearchRequest $request): JsonResponse
    {
        return $this->success(['places' => $this->places->search($request->validated('q'))], 'Places found');
    }

    /** GET /location/geocode?q= - the best match for an address, with its coordinates */
    public function geocode(PlaceSearchRequest $request): JsonResponse
    {
        $place = $this->places->search($request->validated('q'), 1)[0] ?? null;

        return $place ? $this->success(['place' => $place], 'Place found') : $this->error('No place found for this address', 404);
    }

    /** GET /location/reverse-geocode?latitude=&longitude= - the address at a point */
    public function reverse(CoordinatesRequest $request): JsonResponse
    {
        $v = $request->validated();
        $place = $this->places->reverse((float) $v['latitude'], (float) $v['longitude']);

        return $place ? $this->success(['place' => $place], 'Place found') : $this->error('No address found at this location', 404);
    }
}
