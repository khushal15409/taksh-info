<?php

namespace App\Repositories\Eloquent;

use App\Models\Area;
use App\Models\City;
use App\Models\Pincode;
use App\Models\State;
use App\Repositories\Contracts\LocationRepositoryInterface;
use Illuminate\Support\Facades\DB;

class LocationRepository implements LocationRepositoryInterface
{
    public function areaForPincode(string $pincode): ?Area
    {
        return Area::with('city')->where('pincode', trim($pincode))->orderBy('id')->first();
    }

    public function directoryEntry(string $pincode): ?Pincode
    {
        return Pincode::where('pincode', $pincode)->orderBy('id')->first();
    }

    public function registerPincode(array $attributes): Pincode
    {
        return Pincode::create($attributes);
    }

    public function createLocation(string $stateName, string $cityName, string $areaName, string $pincode): array
    {
        return DB::transaction(function () use ($stateName, $cityName, $areaName, $pincode) {
            $state = State::whereRaw('LOWER(name) = ?', [strtolower($stateName)])->first() ?? State::create(['name' => $stateName]);
            $city = City::where('state_id', $state->id)->whereRaw('LOWER(name) = ?', [strtolower($cityName)])->first()
                ?? City::create(['state_id' => $state->id, 'name' => $cityName]);
            $area = Area::create(['city_id' => $city->id, 'name' => $areaName, 'pincode' => $pincode]);

            return ['state_id' => (int) $state->id, 'city_id' => (int) $city->id, 'area_id' => (int) $area->id];
        });
    }
}
