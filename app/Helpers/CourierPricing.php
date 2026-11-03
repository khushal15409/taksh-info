<?php

namespace App\Helpers;

use App\Models\CourierDeliveryPartner;

/** Parcel weights and the legacy local-partner price, exactly as taksh-admin works them out. */
class CourierPricing
{
    public const VOLUMETRIC_DIVISOR_CM = 5000;

    public const INCH_TO_CM = 2.54;

    /** @return array{0: float, 1: float, 2: float} length, width, height in cm */
    public static function dimensionsToCm(float $length, float $width, float $height, string $unit): array
    {
        if ($unit === 'inch') {
            $f = self::INCH_TO_CM;

            return [$length * $f, $width * $f, $height * $f];
        }

        return [$length, $width, $height];
    }

    /** Volumetric weight in grams: (L x W x H cm^3) / 5000 = kg, x 1000 for grams. */
    public static function volumetricWeightGrams(float $length, float $width, float $height, string $unit): float
    {
        [$lc, $wc, $hc] = self::dimensionsToCm($length, $width, $height, $unit);

        return (($lc * $wc * $hc) / self::VOLUMETRIC_DIVISOR_CM) * 1000;
    }

    /** @return array{chargeable_grams: float, source: 'actual'|'volumetric'} the higher of the two weights is charged */
    public static function chargeableWeight(float $actualGrams, float $volumetricGrams): array
    {
        $volumetricHigher = $volumetricGrams >= $actualGrams;

        return ['chargeable_grams' => round($volumetricHigher ? $volumetricGrams : $actualGrams, 3), 'source' => $volumetricHigher ? 'volumetric' : 'actual'];
    }

    public static function priceForPartner(CourierDeliveryPartner $partner, float $chargeableGrams): float
    {
        $amount = (float) $partner->base_charge + (($chargeableGrams / 1000) * (float) $partner->charge_per_kg);

        return round(max($amount, (float) $partner->base_charge), 2);
    }

    public static function toPaise(float|string|null $rupees): int
    {
        return (int) round(((float) $rupees) * 100);
    }
}
