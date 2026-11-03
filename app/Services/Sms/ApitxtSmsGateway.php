<?php

namespace App\Services\Sms;

use App\Exceptions\OtpDeliveryException;
use App\Helpers\Phone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends one time codes through apitxt.com, the same gateway the Taksh API uses. The code goes to the
 * phone and nowhere else: it is never logged, and the auth key (it sits in the request URL) stays out of logs.
 */
class ApitxtSmsGateway implements SmsGateway
{
    public function sendOtp(string $mobile, string $otp): void
    {
        $key = (string) config('services.apitxt.authkey');
        if ($key === '') {
            Log::error('OTP not sent: APITXT_AUTHKEY is not set');
            throw new OtpDeliveryException();
        }

        $digits = preg_replace('/\D+/', '', $mobile);
        if (strlen($digits) === 10) {
            $digits = config('services.apitxt.country_code').$digits;
        }

        try {
            $response = Http::timeout((int) config('services.apitxt.timeout'))
                ->get(rtrim(config('services.apitxt.base_url'), '/').'/sendOTP', [
                    'authkey' => $key,
                    'mobile' => $digits,
                    'otp' => $otp,
                ]);
        } catch (\Throwable) {
            Log::warning('OTP SMS request failed to connect', ['mobile' => Phone::mask($digits)]);
            throw new OtpDeliveryException();
        }

        $body = $response->json();
        $rejected = is_array($body) && (
            in_array(strtolower((string) ($body['type'] ?? $body['status'] ?? '')), ['error', 'failed', 'failure'], true)
            || ($body['success'] ?? true) === false
        );

        if ($response->failed() || $rejected) {
            Log::warning('OTP SMS was rejected', ['mobile' => Phone::mask($digits), 'http_status' => $response->status()]);
            throw new OtpDeliveryException();
        }
    }
}
