<?php

namespace App\Exceptions;

class InvalidOtpException extends ApiException
{
    protected int $status = 400;

    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'api.otp_invalid');
    }
}
