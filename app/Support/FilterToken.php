<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Search and filter terms packed into one opaque token, so `?q=samsung&category[]=3&max=500` never appears in the address bar, history,
 * logs or a Referer header - the URL carries `?f=<token>` instead.
 *
 * The token is the terms as JSON, encrypted and authenticated with AES-256-GCM (key from APP_KEY; the page scope is bound in as
 * additional data, so a "shops" token cannot filter the "listing" page). It is deterministic (same terms -> same token, so URLs are
 * stable and cacheable) and needs no session: a copied URL keeps working for anyone. A damaged or foreign token simply decodes to nothing.
 */
final class FilterToken
{
    public static function enabled(): bool
    {
        return (bool) config('taksh.hide_filters.enabled', true);
    }

    /** @return list<string> the keys of a page scope that are packed into the token */
    public static function keys(string $scope): array
    {
        return (array) config("taksh.hide_filters.scopes.$scope", []);
    }

    /** The scope's keys from an input array, trimmed and limited; empty values are dropped. @return array<string, string|list<string>> */
    public static function clean(string $scope, array $input): array
    {
        $out = [];
        foreach (self::keys($scope) as $key) {
            $v = $input[$key] ?? null;
            if (is_array($v)) {
                $v = array_values(array_filter(array_map(fn ($x) => is_scalar($x) ? mb_substr(trim((string) $x), 0, 100) : '', array_slice($v, 0, 50)), fn ($x) => $x !== ''));
                if ($v) {
                    $out[$key] = $v;
                }
            } elseif (is_scalar($v) && trim((string) $v) !== '') {
                $out[$key] = mb_substr(trim((string) $v), 0, 255);
            }
        }
        ksort($out);

        return $out;
    }

    /** @param array<string, mixed> $filters already clean()ed */
    public static function encode(string $scope, array $filters): string
    {
        if ($filters === []) {
            return '';
        }

        $json = json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $iv = substr(hash_hmac('sha256', $scope.'|'.$json, self::key('iv'), true), 0, 12);
        $cipher = openssl_encrypt($json, 'aes-256-gcm', self::key('enc'), OPENSSL_RAW_DATA, $iv, $tag, $scope, 12);

        return $cipher === false ? '' : rtrim(strtr(base64_encode($iv.$tag.$cipher), '+/', '-_'), '=');
    }

    /** @return array<string, mixed>|null null when the token is not a valid one for this scope */
    public static function decode(string $scope, string $token): ?array
    {
        if ($token === '' || strlen($token) > 2000 || preg_match('/^[A-Za-z0-9_-]+$/', $token) !== 1) {
            return null;
        }

        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false || strlen($raw) < 25) {
            return null;
        }

        $json = openssl_decrypt(substr($raw, 24), 'aes-256-gcm', self::key('enc'), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 12), $scope);
        $data = $json === false ? null : json_decode($json, true);

        return is_array($data) ? self::clean($scope, $data) : null;
    }

    /** The token for the filters the page was opened with (they were put back into the request's query by HideFilters). */
    public static function forRequest(string $scope, Request $request): string
    {
        return self::encode($scope, self::clean($scope, $request->query()));
    }

    /**
     * What a paginator's links should carry: the token (not the terms) plus the visible keys given. With the feature off, the plain terms.
     *
     * @param  array<string, mixed>  $visible  e.g. ['quick' => 1, 'tab' => 'orders']
     * @return array<string, mixed>
     */
    public static function pageQuery(string $scope, Request $request, array $visible = []): array
    {
        $visible = array_filter($visible, fn ($v) => $v !== null && $v !== '' && $v !== []);

        if (! self::enabled()) {
            return $visible + self::clean($scope, $request->query());
        }

        $token = self::forRequest($scope, $request);

        return $token === '' ? $visible : $visible + ['f' => $token];
    }

    private static function key(string $purpose): string
    {
        return hash_hmac('sha256', 'filtertoken|'.$purpose, (string) config('app.key'), true);
    }
}
