<?php

namespace App\Helpers;

/**
 * Rupee amounts to the gateway's minor unit (paise). The amount is rounded to 2 decimals first and then
 * converted through its exact decimal string, never through float arithmetic: 1234.56 * 100 truncates to 123455.
 */
final class Money
{
    public static function toMinorUnits(float|int|string $amount, int $decimals = 2): int
    {
        $text = number_format(round((float) $amount, $decimals), $decimals, '.', '');

        return (int) str_replace('.', '', $text);
    }
}
