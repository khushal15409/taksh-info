<?php

namespace App\Repositories\Contracts;

use App\Models\OtpVerification;
use Carbon\CarbonInterface;

interface OtpRepositoryInterface
{
    public function create(string $mobile, string $hashedOtp, ?string $context, CarbonInterface $expiresAt): OtpVerification;

    public function delete(OtpVerification $otp): void;

    /** Newest code for this mobile that is unused and not expired. */
    public function latestValid(string $mobile): ?OtpVerification;

    public function markUsed(OtpVerification $otp): void;
}
