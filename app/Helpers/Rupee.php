<?php

namespace App\Helpers;

/** Rupee amounts the way the pages show them: "₹1,23,456" (Indian digit grouping), or with paise when asked. */
final class Rupee
{
    public static function format(float|int|string|null $amount, int $decimals = 0): string
    {
        $n = round((float) $amount, $decimals);
        $text = number_format(abs($n), $decimals, '.', '');
        [$int, $frac] = array_pad(explode('.', $text), 2, null);

        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        if ($rest !== '' && $rest !== false) {
            $last3 = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;
        }

        return ($n < 0 ? '-' : '').'₹'.$last3.($frac !== null ? '.'.$frac : '');
    }
}
