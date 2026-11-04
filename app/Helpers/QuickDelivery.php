<?php

namespace App\Helpers;

/** The geography rules of Quick (30 minute) delivery, the same as the Taksh app's: a 10 km radius around the vendor's shop. */
final class QuickDelivery
{
    /** An approved vendor serves a location within this many km of its shop (or with the shop's own pincode). */
    public const RADIUS_KM = 10;

    private const EARTH_RADIUS_KM = 6371.0;

    /** A usable coordinate pair: numeric, in range, and not the (0, 0) "unset" value. */
    public static function validCoordinates(mixed $lat, mixed $lng): bool
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return false;
        }

        [$lat, $lng] = [(float) $lat, (float) $lng];

        if (! is_finite($lat) || ! is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) {
            return false;
        }

        return ! ($lat == 0.0 && $lng == 0.0);
    }

    /** Great-circle (haversine) distance in kilometres. */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $a = min(1.0, max(0.0, $a));

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Inside the radius; coordinates that cannot be judged on either side are never "inside". */
    public static function withinRadius(mixed $lat, mixed $lng, mixed $shopLat, mixed $shopLng, float $radiusKm = self::RADIUS_KM): bool
    {
        if (! self::validCoordinates($lat, $lng) || ! self::validCoordinates($shopLat, $shopLng)) {
            return false;
        }

        return self::distanceKm((float) $lat, (float) $lng, (float) $shopLat, (float) $shopLng) <= $radiusKm;
    }

    /** A pincode that can be matched: six digits (000000 is the app's "device location has none"). */
    public static function normalizePincode(mixed $pincode): ?string
    {
        $pincode = trim((string) $pincode);

        return preg_match('/^\d{6}$/', $pincode) && $pincode !== '000000' ? $pincode : null;
    }
}
