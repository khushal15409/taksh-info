<?php

namespace App\Repositories\Contracts;

use App\Models\Area;
use App\Models\Pincode;

/** State / city / area / pincode data shared with taksh-admin. */
interface LocationRepositoryInterface
{
    /** The first registered area (with its city) for a pincode. */
    public function areaForPincode(string $pincode): ?Area;

    /** The postal-directory entry for a pincode. */
    public function directoryEntry(string $pincode): ?Pincode;

    /** Add a pincode to the directory (always inactive: it never becomes serviceable on its own). */
    public function registerPincode(array $attributes): Pincode;

    /** Find or create the state and city, and create the area; returns their ids. @return array{state_id:int, city_id:int, area_id:int} */
    public function createLocation(string $stateName, string $cityName, string $areaName, string $pincode): array;
}
