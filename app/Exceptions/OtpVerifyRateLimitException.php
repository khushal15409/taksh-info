<?php

namespace App\Exceptions;

class OtpVerifyRateLimitException extends ApiException
{
    protected int $status = 429;

    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'api.otp_verify_rate_limit');
    }
}
