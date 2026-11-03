<?php

namespace App\Services\Shiprocket;

use RuntimeException;

/**
 * A failed Shiprocket call. The message is always generic and safe to log; it never
 * contains credentials, tokens or raw response bodies. Callers should show customers
 * their own wording, not this message.
 */
class ShiprocketException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';
    public const CONNECTION = 'connection';
    public const AUTHENTICATION = 'authentication';
    public const CLIENT_ERROR = 'client_error';
    public const SERVER_ERROR = 'server_error';
    public const INVALID_RESPONSE = 'invalid_response';
    public const NO_SERVICE = 'no_service';

    public function __construct(
        string $message,
        public readonly string $category,
        public readonly ?int $httpStatus = null,
        public readonly ?array $errors = null,
        /** Shiprocket's own message, for classifying a refusal only. Never log it or show it to a customer. */
        public readonly ?string $providerMessage = null,
    ) {
        parent::__construct($message);
    }
}
