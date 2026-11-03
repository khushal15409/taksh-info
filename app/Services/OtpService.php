<?php

namespace App\Services;

use App\Helpers\Otp;
use App\Helpers\Phone;
use App\Repositories\Contracts\OtpRepositoryInterface;
use App\Services\Sms\SmsGateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Makes, texts and checks one time codes against the shared `otp_verifications` table,
 * with the same rules as the Taksh API (hashed, expiring, single use).
 */
class OtpService
{
    public function __construct(
        private readonly OtpRepositoryInterface $otps,
        private readonly SmsGateway $sms,
    ) {}

    /**
     * Make a code for this mobile, keep it hashed until used or expired and text it.
     * The code itself is never returned - it goes to the phone, and nowhere else.
     *
     * @return array{expires_at: Carbon, message: string}
     */
    public function sendOtp(string $mobile, ?string $context = null): array
    {
        $otp = Otp::generate();
        $expiresAt = Carbon::now()->addMinutes(Otp::minutes());

        $record = $this->otps->create($mobile, Hash::make($otp), $context, $expiresAt);

        try {
            $this->sms->sendOtp($mobile, $otp);
        } catch (\Throwable $e) {
            // A code that never reached the phone must not stay valid.
            $this->otps->delete($record);
            throw $e;
        }

        Log::info('OTP sent', ['mobile' => Phone::mask($mobile), 'context' => $context]);

        return ['expires_at' => $expiresAt, 'message' => 'OTP sent to the mobile number.'];
    }

    /** True only for the newest unused, unexpired code of this mobile; that code is then marked used. */
    public function verifyOtp(string $mobile, string $otp, bool $markAsUsed = true): bool
    {
        $record = $this->otps->latestValid($mobile);

        if (! $record || ! Hash::check($otp, $record->otp)) {
            return false;
        }

        if ($markAsUsed) {
            $this->otps->markUsed($record);
        }

        return true;
    }
}
