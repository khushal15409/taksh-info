<?php

namespace App\Helpers;

/**
 * The one time code someone types in to sign in. Always random, never a fixed or guessable value.
 */
class Otp
{
    /** A fresh random code (4 digits unless AUTH_OTP_LENGTH says otherwise). */
    public static function generate(): string
    {
        return str_pad((string) random_int(0, (10 ** self::length()) - 1), self::length(), '0', STR_PAD_LEFT);
    }

    public static function length(): int
    {
        return max(4, (int) config('otp.length', 4));
    }

    /** How long a code lasts. */
    public static function minutes(): int
    {
        return max(1, (int) config('otp.expires_in_minutes', 5));
    }
}
