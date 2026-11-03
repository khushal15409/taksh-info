<?php

namespace App\Support;

use App\Repositories\Contracts\SettingRepositoryInterface;

/**
 * Which ways a customer may pay, set separately for each kind of order (standard / quick) in taksh-admin.
 * Nothing saved means nothing has been switched off, so a fresh install offers every method.
 */
class PaymentOptions
{
    public const ORDER_TYPES = ['standard', 'quick'];

    public const METHODS = ['cod', 'online'];

    public static function enabled(string $orderType, string $method): bool
    {
        $saved = app(SettingRepositoryInterface::class)->value('payment_option_'.$orderType.'_'.$method);

        return $saved === null || $saved === '' ? true : (bool) (int) $saved;
    }

    /** @return array<string, array<string, bool>> */
    public static function all(): array
    {
        $out = [];

        foreach (self::ORDER_TYPES as $type) {
            foreach (self::METHODS as $method) {
                $out[$type][$method] = self::enabled($type, $method);
            }
        }

        return $out;
    }

    /** Which kind of order a delivery_type belongs to. */
    public static function orderTypeFor(?string $deliveryType): string
    {
        return in_array((string) $deliveryType, ['normal', '1_day'], true) ? 'standard' : 'quick';
    }
}
