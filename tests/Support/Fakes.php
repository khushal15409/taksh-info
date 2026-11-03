<?php

namespace Tests\Support;

use App\Exceptions\OtpDeliveryException;
use App\Models\OtpVerification;
use App\Models\User;
use App\Repositories\Contracts\OtpRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Sms\SmsGateway;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\PresenceVerifierInterface;

/** In-memory stand-ins so auth can be tested without touching the shared database. */
class FakeUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    public array $users = [];

    private int $nextId = 1;

    public function firstOrCreateCustomer(string $mobile): User
    {
        return $this->users[$mobile] ??= tap(new User([
            'mobile' => $mobile,
            'name' => 'User '.$mobile,
            'user_type' => User::TYPE_CUSTOMER,
            'is_verified' => true,
            'status' => User::STATUS_ACTIVE,
        ]), fn (User $u) => $u->id = $this->nextId++);
    }

    public function seed(array $attributes): User
    {
        $user = new User($attributes);
        $user->id = $this->nextId++;

        return $this->users[$user->mobile] = $user;
    }

    public function save(User $user): User
    {
        return $this->users[$user->mobile] = $user;
    }

    public function update(User $user, array $attributes): User
    {
        $user->fill($attributes);

        return $this->save($user);
    }
}

class FakeOtpRepository implements OtpRepositoryInterface
{
    /** @var list<OtpVerification> */
    public array $rows = [];

    public function create(string $mobile, string $hashedOtp, ?string $context, CarbonInterface $expiresAt): OtpVerification
    {
        return $this->rows[] = new OtpVerification([
            'mobile' => $mobile, 'otp' => $hashedOtp, 'context' => $context, 'expires_at' => $expiresAt, 'is_used' => false,
        ]);
    }

    public function delete(OtpVerification $otp): void
    {
        $this->rows = array_values(array_filter($this->rows, fn ($r) => $r !== $otp));
    }

    public function latestValid(string $mobile): ?OtpVerification
    {
        $valid = array_filter($this->rows, fn ($r) => $r->mobile === $mobile && ! $r->is_used && $r->expires_at->gt(Carbon::now()));

        return $valid ? end($valid) : null;
    }

    public function markUsed(OtpVerification $otp): void
    {
        $otp->is_used = true;
    }
}

class FakeRoleRepository implements RoleRepositoryInterface
{
    /** @var array<string, list<string>> mobile => roles */
    public array $roles = [];

    public function hasAnyRole(User $user, array $names): bool
    {
        return (bool) array_intersect($this->roles[$user->mobile] ?? [], $names);
    }

    public function hasRole(User $user, string $name): bool
    {
        return in_array($name, $this->roles[$user->mobile] ?? [], true);
    }

    public function assignRole(User $user, string $name): void
    {
        $this->roles[$user->mobile][] = $name;
    }
}

class FakeSmsGateway implements SmsGateway
{
    public array $sent = [];

    public bool $fail = false;

    public function sendOtp(string $mobile, string $otp): void
    {
        if ($this->fail) {
            throw new OtpDeliveryException();
        }
        $this->sent[] = [$mobile, $otp];
    }

    public function lastCode(): ?string
    {
        return $this->sent ? end($this->sent)[1] : null;
    }
}

/** Lets `unique:` rules pass without a database. */
class FakePresenceVerifier implements PresenceVerifierInterface
{
    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = []): int
    {
        return 0;
    }

    public function getMultiCount($collection, $column, array $values, array $extra = []): int
    {
        return 0;
    }
}
