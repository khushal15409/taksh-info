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
        // The Taksh charge depends on how much the item sells for.
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

    /** Cheap items carry the smaller charge, so the fee never swallows the item. */
    public static function takshChargeFor(float $sellingPrice): float
    {
        return $sellingPrice < self::value('taksh_charge_threshold')
            ? self::value('taksh_charge_below')
            : self::value('taksh_charge_above');
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
