<?php

namespace App\Services;

use App\Exceptions\InvalidOtpException;
use App\Exceptions\OtpRateLimitException;
use App\Exceptions\OtpVerifyRateLimitException;
use App\Models\User;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Customer OTP sign-in, following the Taksh API's AuthController step for step:
 * send code -> verify -> find or auto-register the customer -> keep staff roles intact
 * -> give the customer role -> merge the guest cart.
 */
class AuthService
{
    private const CONTEXT = 'customer_login';

    public function __construct(
        private readonly OtpService $otp,
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly CartRepositoryInterface $carts,
    ) {}

    /** @return array{expires_at: \Carbon\Carbon, message: string} */
    public function sendOtp(string $mobile): array
    {
        $key = 'send-otp:'.$mobile;

        if (RateLimiter::tooManyAttempts($key, (int) config('otp.send_attempts_per_minute'))) {
            throw new OtpRateLimitException();
        }

        RateLimiter::hit($key, 60);

        return $this->otp->sendOtp($mobile, self::CONTEXT);
    }

    /** Verify the code and return the (possibly new) customer. */
    public function verifyOtp(string $mobile, string $otp, ?string $guestToken = null): User
    {
        $key = 'verify-otp:'.$mobile;

        if (RateLimiter::tooManyAttempts($key, (int) config('otp.verify_attempts_per_minute'))) {
            throw new OtpVerifyRateLimitException();
        }

        if (! $this->otp->verifyOtp($mobile, $otp)) {
            RateLimiter::hit($key, 60);
            throw new InvalidOtpException();
        }

        RateLimiter::clear($key);

        $user = $this->users->firstOrCreateCustomer($mobile);
        $isStaff = $this->roles->hasAnyRole($user, User::STAFF_ROLES);

        // This is customer sign-in: never override the type of an admin, vendor or salesman.
        if (is_null($user->user_type) && ! $isStaff) {
            $user->user_type = User::TYPE_CUSTOMER;
        }
        if (! $user->is_verified) {
            $user->is_verified = true;
        }
        if ($user->status !== User::STATUS_ACTIVE) {
            $user->status = User::STATUS_ACTIVE;
        }
        $this->users->save($user);

        if (! $isStaff && ! $this->roles->hasRole($user, 'customer')) {
            $this->roles->assignRole($user, 'customer');
        }

        if ($guestToken) {
            $this->carts->mergeGuestCartIntoUser($user->id, $guestToken);
        }

        return $user;
    }
}
