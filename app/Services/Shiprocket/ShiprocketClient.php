<?php

namespace App\Services\Shiprocket;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Authenticated HTTP transport for the Shiprocket API. It knows nothing about orders,
 * rates or shipments: future Shiprocket services call get()/post() and get decoded JSON.
 *
 * - Logs in with the configured API user and caches the token (never in the database).
 * - On a 401 it drops the cached token, logs in again and retries that request once.
 * - Connection failures are retried once, but only for read-only calls (GET and login), which are safe to repeat.
 *   Order-changing POSTs are never retried here: the shipment service owns that decision.
 * - 4xx (other than the 401 above) and 5xx are not retried.
 */
class ShiprocketClient
{
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('get', $endpoint, $query);
    }

    public function post(string $endpoint, array $payload = []): array
    {
        return $this->request('post', $endpoint, $payload);
    }

    public function isConfigured(): bool
    {
        return filled(config('shiprocket.base_url')) && filled(config('shiprocket.email')) && filled(config('shiprocket.password'));
    }

    /** Drop the cached token so the next call logs in again. */
    public function forgetToken(): void
    {
        Cache::forget((string) config('shiprocket.token_cache_key'));
    }

    private function request(string $method, string $endpoint, array $data): array
    {
        $this->assertConfigured();

        $response = $this->send($method, $endpoint, $data, $this->token());

        if ($response->status() === 401) {
            $this->forgetToken();
            $this->logFailure('token rejected, re-authenticating once', $endpoint, 401, ShiprocketException::AUTHENTICATION);
            $response = $this->send($method, $endpoint, $data, $this->token());
        }

        return $this->decode($response, $endpoint);
    }

    private function send(string $method, string $endpoint, array $data, string $token): Response
    {
        try {
            $request = $this->http()->withToken($token);
            if ($method === 'get') {
                // Safe to repeat: one more try when the connection drops or times out.
                $request = $request->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false);
            }

            return $request->{$method}($this->url($endpoint), $data);
        } catch (ConnectionException) {
            $this->logFailure('connection failed or timed out', $endpoint, null, ShiprocketException::CONNECTION);

            throw new ShiprocketException('Could not reach Shiprocket.', ShiprocketException::CONNECTION);
        }
    }

    private function token(): string
    {
        $key = (string) config('shiprocket.token_cache_key');
        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = $this->http()->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false)->post($this->url('auth/login'), [
                'email' => config('shiprocket.email'),
                'password' => config('shiprocket.password'),
            ]);
        } catch (ConnectionException) {
            $this->logFailure('connection failed or timed out', 'auth/login', null, ShiprocketException::CONNECTION);

            throw new ShiprocketException('Could not reach Shiprocket.', ShiprocketException::CONNECTION);
        }

        if ($response->failed()) {
            $this->logFailure('login rejected', 'auth/login', $response->status(), ShiprocketException::AUTHENTICATION);

            throw new ShiprocketException('Shiprocket authentication failed.', ShiprocketException::AUTHENTICATION, $response->status());
        }

        $token = $response->json('token');
        if (! is_string($token) || $token === '') {
            $this->logFailure('login response had no token', 'auth/login', $response->status(), ShiprocketException::INVALID_RESPONSE);

            throw new ShiprocketException('Shiprocket authentication response was invalid.', ShiprocketException::INVALID_RESPONSE, $response->status());
        }

        $ttl = $this->ttlSeconds($token);
        if ($ttl > 0) {
            Cache::put($key, $token, $ttl);
        }

        return $token;
    }

    /** Seconds to cache: the JWT's own expiry when readable, else the configured fallback, minus a safety buffer. */
    private function ttlSeconds(string $token): int
    {
        $buffer = max(0, (int) config('shiprocket.token_safety_buffer_minutes')) * 60;
        $exp = $this->jwtExpiry($token);
        $seconds = $exp !== null ? $exp - time() : max(0, (int) config('shiprocket.token_ttl_minutes')) * 60;

        return max(0, $seconds - $buffer);
    }

    private function jwtExpiry(string $token): ?int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($claims) && isset($claims['exp']) && is_numeric($claims['exp']) ? (int) $claims['exp'] : null;
    }

    private function decode(Response $response, string $endpoint): array
    {
        $status = $response->status();

        if ($response->serverError()) {
            $this->logFailure('server error', $endpoint, $status, ShiprocketException::SERVER_ERROR);

            throw new ShiprocketException('Shiprocket is temporarily unavailable.', ShiprocketException::SERVER_ERROR, $status);
        }

        if ($response->clientError()) {
            $category = $status === 401 ? ShiprocketException::AUTHENTICATION : ShiprocketException::CLIENT_ERROR;
            $this->logFailure('request rejected', $endpoint, $status, $category);

            $errors = $response->json('errors');
            $message = $response->json('message');
            throw new ShiprocketException('Shiprocket rejected the request.', $category, $status, is_array($errors) ? $errors : null, is_string($message) ? mb_substr($message, 0, 300) : null);
        }

        $json = $response->json();
        if (! is_array($json)) {
            $this->logFailure('response was not valid JSON', $endpoint, $status, ShiprocketException::INVALID_RESPONSE);

            throw new ShiprocketException('Shiprocket returned an invalid response.', ShiprocketException::INVALID_RESPONSE, $status);
        }

        return $json;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout((int) config('shiprocket.timeout'))
            ->connectTimeout((int) config('shiprocket.connect_timeout'));
    }

    private function url(string $endpoint): string
    {
        return rtrim((string) config('shiprocket.base_url'), '/').'/'.ltrim($endpoint, '/');
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new ShiprocketException('Shiprocket is not configured.', ShiprocketException::NOT_CONFIGURED);
        }
    }

    /** Only endpoint, status and category are logged: never headers, bodies, credentials or tokens. */
    private function logFailure(string $event, string $endpoint, ?int $status, string $category): void
    {
        Log::warning('Shiprocket: '.$event, ['endpoint' => $endpoint, 'status' => $status, 'category' => $category]);
    }
}
