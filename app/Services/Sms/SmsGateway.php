<?php

namespace App\Services\Sms;

interface SmsGateway
{
    /** Text a one time code to a 10 digit mobile. Throws OtpDeliveryException when it cannot be delivered. */
    public function sendOtp(string $mobile, string $otp): void;
}
