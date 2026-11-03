<?php

namespace App\Helpers;

/**
 * Indian mobile numbers are stored as 10 digits (users.mobile). These helpers
 * turn whatever a person types into that form, and back into a readable one.
 */
class Phone
{
    /** "+91 98765 43210", "098765-43210" ... -> "9876543210"; null when it is not a 10 digit mobile. */
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[0-9]{10}$/', $digits) ? $digits : null;
    }

    /** "9876543210" -> "+91 98765 43210". */
    public static function display(?string $mobile): string
    {
        $mobile = self::normalize($mobile);

        return $mobile ? '+91 '.substr($mobile, 0, 5).' '.substr($mobile, 5) : '';
    }

    /** "9876543210" -> "98******10" for logs. */
    public static function mask(string $mobile): string
    {
        return substr($mobile, 0, 2).str_repeat('*', max(0, strlen($mobile) - 4)).substr($mobile, -2);
    }
}
