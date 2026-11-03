<?php

namespace App\Support;

/** Standard and Quick (30 minute) products live in separate carts, told apart by the delivery type code. */
final class DeliveryBucket
{
    public const QUICK = '30_min';

    public const STANDARD = 'normal';

    public static function isQuick(?string $deliveryType): bool
    {
        return $deliveryType === self::QUICK;
    }

    /** 'express' for the quick code, 'standard' for any other code, null when none was given. */
    public static function of(?string $deliveryType): ?string
    {
        if ($deliveryType === null || $deliveryType === '') {
            return null;
        }

        return self::isQuick($deliveryType) ? 'express' : 'standard';
    }

    public static function given(?string $deliveryType): bool
    {
        return $deliveryType !== null && $deliveryType !== '';
    }
}
