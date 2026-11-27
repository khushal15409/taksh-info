<?php

namespace App\Helpers;

use App\Repositories\Contracts\SettingRepositoryInterface;

/**
 * The Taksh charge that sits on top of a vendor's selling price, worked out from the band admin sets in
 * Settings > Vendor Charges (same keys and fallbacks as taksh-admin; only the rates the website needs).
 */
class VendorCharges
{
    /** key => shipped default (same keys and defaults as Settings > Vendor Charges in taksh-admin) */
    public const RATES = [
        // The old single price break. Still read by CatalogRepository's price filter until it
        // moves to takshChargeSql(); the charge itself comes from the bands.
        'taksh_charge_threshold' => 100,
        'taksh_charge_below' => 49,
        'taksh_charge_above' => 79,
        // GST on the Taksh charge (a platform service fee has its own rate).
        'taksh_charge_gst_percent' => 18,
        // The payment gateway's cut, taken off the selling price, and the GST on it.
        'gateway_percent' => 2,
        'gateway_gst_percent' => 18,
        // Income tax: nothing is held back from a vendor until the year's sales cross this.
        'tds_194o_threshold' => 500000,
        // Charged back to the vendor when an order comes home.
        'default_return_charge' => 79,
        // How long a customer has to send something back (days).
        'default_return_days' => 7,
    ];

    /** Settings key holding the Taksh charge price bands (JSON), set in taksh-admin. */
    public const BANDS_KEY = 'taksh_charge_bands';

    /** Bands used until admin saves their own: under ₹200 -> ₹20, ₹200 and above -> ₹59. */
    public const DEFAULT_BANDS = [
        ['from' => 0, 'to' => 200, 'charge' => 20],
        ['from' => 200, 'to' => null, 'charge' => 59],
    ];

    /** Bands read once per request. */
    private static ?array $bands = null;

    /**
     * The Taksh charge for one selling price, from the price band it falls in.
     * Same rule as taksh-admin's VendorCharges::takshChargeFor().
     */
    public static function takshChargeFor(float $sellingPrice): float
    {
        foreach (self::takshBands() as $band) {
            if ($band['to'] === null || $sellingPrice < $band['to']) {
                return (float) $band['charge'];
            }
        }

        return 0;
    }

    /** The price bands admin set, lowest first; DEFAULT_BANDS when none are saved. */
    public static function takshBands(): array
    {
        if (self::$bands !== null) {
            return self::$bands;
        }

        $saved = json_decode((string) app(SettingRepositoryInterface::class)->value(self::BANDS_KEY), true);

        return self::$bands = is_array($saved) && $saved !== [] && end($saved)['to'] === null ? $saved : self::DEFAULT_BANDS;
    }

    /** SQL CASE giving the band charge for a selling price SQL expression (for price filters in queries). */
    public static function takshChargeSql(string $sellingSql): string
    {
        $sql = '(CASE';

        foreach (self::takshBands() as $band) {
            $sql .= $band['to'] === null
                ? ' ELSE '.(float) $band['charge']
                : ' WHEN '.$sellingSql.' < '.(float) $band['to'].' THEN '.(float) $band['charge'];
        }

        return $sql.' END)';
    }

    /** One rate, falling back to the value we ship with. */
    public static function value(string $key): float
    {
        $saved = app(SettingRepositoryInterface::class)->value($key);

        return $saved === null || $saved === '' ? (float) self::RATES[$key] : (float) $saved;
    }

    /** The gateway's cut on a selling price, and the GST on that cut. @return array{gateway_fee: float, gateway_fee_gst: float} */
    public static function gatewayFeeOn(float $sellingPrice): array
    {
        $fee = $sellingPrice * self::value('gateway_percent') / 100;
        $gst = $fee * self::value('gateway_gst_percent') / 100;

        return ['gateway_fee' => round($fee, 2), 'gateway_fee_gst' => round($gst, 2)];
    }
}
