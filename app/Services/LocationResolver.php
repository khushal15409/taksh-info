<?php

namespace App\Services;

use App\Repositories\Contracts\LocationRepositoryInterface;
use Illuminate\Support\Str;

/**
 * State, city and area for a pincode, exactly as taksh-admin works them out:
 * the registered area of the pincode; else the three ids the client sent (all three or none);
 * else the postal directory, creating the matching state / city / area for a real pincode;
 * else a well-formed pincode with state and city names is registered inactive. Nothing is guessed.
 */
class LocationResolver
{
    public function __construct(private readonly LocationRepositoryInterface $locations) {}

    /** @return array{state_id:int, city_id:int, area_id:int}|null */
    public function resolve(string $pincode, $stateId = null, $cityId = null, $areaId = null, $stateName = null, $cityName = null, $areaName = null): ?array
    {
        $area = $this->locations->areaForPincode($pincode);

        if ($area && $area->city) {
            return ['state_id' => (int) $area->city->state_id, 'city_id' => (int) $area->city_id, 'area_id' => (int) $area->id];
        }

        if ($stateId && $cityId && $areaId) {
            return ['state_id' => (int) $stateId, 'city_id' => (int) $cityId, 'area_id' => (int) $areaId];
        }

        return $this->fromPostalDirectory(trim($pincode), $stateName, $cityName, $areaName);
    }

    private function fromPostalDirectory(string $pincode, $stateName, $cityName, $areaName): ?array
    {
        $entry = $this->locations->directoryEntry($pincode);

        if (! $entry) {
            // Unknown to the directory too: only a well-formed Indian pincode with both names (picked on the map) is accepted,
            // and the row is created inactive so it never becomes serviceable on its own.
            $stateName = trim((string) $stateName);
            $cityName = trim((string) $cityName);

            if (! preg_match('/^[1-9][0-9]{5}$/', $pincode) || $stateName === '' || $cityName === '') {
                return null;
            }

            $entry = $this->locations->registerPincode([
                'officename' => trim((string) $areaName) !== '' ? trim((string) $areaName) : $cityName,
                'pincode' => $pincode,
                'delivery' => 'Delivery',
                'district' => $cityName,
                'statename' => $stateName,
                'status' => 0,
            ]);
        }

        if (trim((string) $entry->statename) === '' || trim((string) $entry->district) === '') {
            return null;
        }

        $state = Str::title(strtolower(trim($entry->statename)));
        $city = Str::title(strtolower(trim($entry->district)));
        $area = trim((string) $entry->officename) !== '' ? trim($entry->officename) : $city;

        return $this->locations->createLocation($state, $city, $area, $pincode);
    }
}
