<?php

namespace App\Exceptions;

class OtpRateLimitException extends ApiException
{
    protected int $status = 429;

    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'api.otp_rate_limit');
    }
}
