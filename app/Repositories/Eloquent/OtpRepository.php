<?php

namespace App\Repositories\Eloquent;

use App\Models\OtpVerification;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class OtpRepository implements OtpRepositoryInterface
{
    public function create(string $mobile, string $hashedOtp, ?string $context, CarbonInterface $expiresAt): OtpVerification
    {
        return OtpVerification::create([
            'mobile' => $mobile,
            'otp' => $hashedOtp,
            'context' => $context,
            'expires_at' => $expiresAt,
            'is_used' => false,
        ]);
    }

    public function delete(OtpVerification $otp): void
    {
        $otp->delete();
    }

    public function latestValid(string $mobile): ?OtpVerification
    {
        return OtpVerification::where('mobile', $mobile)
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();
    }

    public function markUsed(OtpVerification $otp): void
    {
        $otp->update(['is_used' => true]);
    }
}
