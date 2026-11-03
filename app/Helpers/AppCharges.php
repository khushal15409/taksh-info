<?php

namespace App\Helpers;

use App\Repositories\Contracts\SettingRepositoryInterface;

/**
 * Fees added on top of an order. Admin sets them in Settings > App Charges; a fee that is switched
 * off is simply 0, so callers can always add what they are given.
 */
class AppCharges
{
    /** Charged once on every order. */
    public static function platformFee(): float
    {
        return self::amount('platform_fee');
    }

    /** Charged once more on a 30 minute order. */
    public static function quickDeliveryFee(): float
    {
        return self::amount('quick_delivery_fee');
    }

    /** What an order adds: the platform fee, plus the quick delivery fee on a 30 minute order. */
    public static function forOrder(bool $isQuick = false): array
    {
        return [
            'platform_fee' => self::platformFee(),
            'quick_delivery_fee' => $isQuick ? self::quickDeliveryFee() : 0.0,
        ];
    }

    /** The amount when the switch is on, otherwise 0. */
    public static function amount(string $fee): float
    {
        return self::isOn($fee) ? round((float) self::read($fee.'_amount'), 2) : 0.0;
    }

    public static function isOn(string $fee): bool
    {
        return (int) self::read($fee.'_enabled') === 1;
    }

    private static function read(string $key): ?string
    {
        return app(SettingRepositoryInterface::class)->value($key);
    }
}
