<?php

namespace App\Repositories\Contracts;

use App\Models\Vendor;
use Illuminate\Support\Collection;

/** Approved vendors, as far as Quick delivery needs to know where their shops are. */
interface QuickVendorRepositoryInterface
{
    /** Approved vendors whose shop latitude lies within [$lat - $delta, $lat + $delta] (rows: id, shop_latitude, shop_longitude). */
    public function approvedInLatitudeBand(float $lat, float $delta): Collection;

    public function vendor(int $id): ?Vendor;

    /** Ids of approved vendors whose shop has this pincode. @return int[] */
    public function approvedIdsByShopPincode(string $pincode): array;
}
