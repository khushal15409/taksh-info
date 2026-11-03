<?php

namespace App\Exceptions;

class OtpDeliveryException extends ApiException
{
    protected int $status = 503;

    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'api.otp_delivery_failed');
    }
}
