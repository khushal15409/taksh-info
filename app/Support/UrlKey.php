<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Opaque, reversible keys for page URLs, so a row number never shows in an address bar: 12 becomes e.g. "k3x9a2b7c1d".
 *
 * Not stored anywhere (no database change): the number is scrambled with a keyed 4-round Feistel permutation (so neighbouring ids look
 * unrelated) and a keyed checksum is added (so a guessed or edited key is rejected, not mapped to some other row). Each scope
 * (shop, product, order ...) has its own key, so an order key can never open a product page.
 *
 * This hides and de-sequences ids; it is NOT access control - private pages still check that the row belongs to the signed-in customer.
 */
final class UrlKey
{
    public const LENGTH = 11;   // 7 characters of scrambled id + 4 of checksum

    /** @var array<string, string> */
    private static array $keys = [];

    public static function enabled(): bool
    {
        return (bool) config('taksh.opaque_urls.enabled', true);
    }

    public static function legacyNumeric(): bool
    {
        return (bool) config('taksh.opaque_urls.legacy_numeric', true);
    }

    public static function encode(string $scope, int $id): string
    {
        if ($id < 0 || $id > 0xFFFFFFFF) {
            return (string) $id;   // outside the 32-bit range the scheme covers: keep the plain number
        }

        $mixed = self::permute($scope, $id);

        return str_pad(base_convert((string) $mixed, 10, 36), 7, '0', STR_PAD_LEFT).self::tag($scope, $mixed);
    }

    /** The id behind a key (or "slug-key"), or null when it is not a key made for this scope. */
    public static function decode(string $scope, string $value): ?int
    {
        $code = self::codeOf($value);
        if ($code === null) {
            return null;
        }

        $mixed = (int) base_convert(substr($code, 0, 7), 36, 10);
        if ($mixed > 0xFFFFFFFF || ! hash_equals(self::tag($scope, $mixed), substr($code, 7))) {
            return null;
        }

        return self::unpermute($scope, $mixed);
    }

    /** "relish-k3x9a2b7c1d": a readable name in front of the key (the name is decoration only; the key alone finds the row). */
    public static function slug(string $scope, int $id, ?string $name): string
    {
        $slug = Str::limit(Str::slug((string) $name), 60, '');

        return ($slug !== '' ? rtrim($slug, '-').'-' : '').self::encode($scope, $id);
    }

    /** The key part of "key" or "slug-key"; null when the text is not shaped like one. */
    public static function codeOf(string $value): ?string
    {
        $code = str_contains($value, '-') ? substr($value, strrpos($value, '-') + 1) : $value;

        return preg_match('/^[0-9a-z]{'.self::LENGTH.'}$/', $code) === 1 ? $code : null;
    }

    private static function key(string $scope): string
    {
        return self::$keys[$scope] ??= hash_hmac('sha256', 'urlkey|'.$scope, (string) config('app.key'), true);
    }

    private static function round(string $scope, int $round, int $half): int
    {
        return unpack('n', substr(hash_hmac('sha256', pack('Cn', $round, $half), self::key($scope), true), 0, 2))[1];
    }

    private static function permute(string $scope, int $id): int
    {
        [$l, $r] = [$id >> 16, $id & 0xFFFF];
        for ($i = 0; $i < 4; $i++) {
            [$l, $r] = [$r, $l ^ self::round($scope, $i, $r)];
        }

        return ($l << 16) | $r;
    }

    private static function unpermute(string $scope, int $mixed): int
    {
        [$l, $r] = [$mixed >> 16, $mixed & 0xFFFF];
        for ($i = 3; $i >= 0; $i--) {
            [$l, $r] = [$r ^ self::round($scope, $i, $l), $l];
        }

        return ($l << 16) | $r;
    }

    private static function tag(string $scope, int $mixed): string
    {
        $n = unpack('N', substr(hash_hmac('sha256', 'tag|'.$mixed, self::key($scope), true), 0, 4))[1] % (36 ** 4);

        return str_pad(base_convert((string) $n, 10, 36), 4, '0', STR_PAD_LEFT);
    }
}
