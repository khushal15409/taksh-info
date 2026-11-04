<?php

namespace App\Services\Quick;

use App\Helpers\QuickDelivery;
use App\Repositories\Contracts\QuickVendorRepositoryInterface;

/**
 * Which vendors can serve a location with Quick delivery - the rule the Taksh app uses (taksh-admin's QuickServiceability):
 * an approved vendor serves a location within 10 km of its shop, or one that carries the shop's own pincode.
 */
class QuickServiceability
{
    public function __construct(private readonly QuickVendorRepositoryInterface $vendors) {}

    /**
     * Ids of the vendors that serve the location, nearest first (the ones serving only by pincode come after).
     * No usable coordinates and no usable pincode means nobody serves it.
     *
     * @return int[]
     */
    public function vendorIds(mixed $lat, mixed $lng, mixed $pincode = null): array
    {
        $ids = [];

        if (QuickDelivery::validCoordinates($lat, $lng)) {
            $lat = (float) $lat;
            $lng = (float) $lng;
            $band = (QuickDelivery::RADIUS_KM / 111.0) * 1.05;   // 1 degree of latitude is ~111 km

            $inRange = [];
            foreach ($this->vendors->approvedInLatitudeBand($lat, $band) as $vendor) {
                if (QuickDelivery::validCoordinates($vendor->shop_latitude, $vendor->shop_longitude)) {
                    $km = QuickDelivery::distanceKm($lat, $lng, (float) $vendor->shop_latitude, (float) $vendor->shop_longitude);
                    if ($km <= QuickDelivery::RADIUS_KM) {
                        $inRange[(int) $vendor->id] = $km;
                    }
                }
            }
            asort($inRange);
            $ids = array_keys($inRange);
        }

        $pincode = QuickDelivery::normalizePincode($pincode);
        if ($pincode !== null) {
            $ids = array_values(array_unique(array_merge($ids, $this->vendors->approvedIdsByShopPincode($pincode))));
        }

        return $ids;
    }

    /** Can this vendor serve the location: within the radius of its shop, or the shop's own pincode. */
    public function vendorServes(\App\Models\Vendor $vendor, mixed $lat, mixed $lng, mixed $pincode = null): bool
    {
        if ($vendor->status !== 'approved') {
            return false;
        }

        if (QuickDelivery::withinRadius($lat, $lng, $vendor->shop_latitude, $vendor->shop_longitude)) {
            return true;
        }

        $pincode = QuickDelivery::normalizePincode($pincode);

        return $pincode !== null && QuickDelivery::normalizePincode($vendor->shop_pincode) === $pincode;
    }

    /** Does every one of these vendors serve the place? (an address Quick can be ordered to for a whole cart) */
    public function servesAll(array $vendorIds, mixed $lat, mixed $lng, mixed $pincode = null): bool
    {
        foreach (array_unique($vendorIds) as $id) {
            $vendor = $this->vendors->vendor((int) $id);
            if (! $vendor || ! $this->vendorServes($vendor, $lat, $lng, $pincode)) {
                return false;
            }
        }

        return $vendorIds !== [];
    }
}
