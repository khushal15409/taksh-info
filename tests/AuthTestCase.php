<?php

namespace Tests;

use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\FakeCartRepository;
use Tests\Support\FakeOtpRepository;
use Tests\Support\FakePresenceVerifier;
use Tests\Support\FakeRoleRepository;
use Tests\Support\FakeSmsGateway;
use Tests\Support\FakeUserRepository;

require_once __DIR__.'/Support/Fakes.php';
require_once __DIR__.'/Support/CartFakes.php';

abstract class AuthTestCase extends TestCase
{
    protected FakeUserRepository $users;

    protected FakeOtpRepository $otps;

    protected FakeRoleRepository $roles;

    protected FakeCartRepository $carts;

    protected FakeSmsGateway $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(UserRepositoryInterface::class, $this->users = new FakeUserRepository);
        $this->app->instance(OtpRepositoryInterface::class, $this->otps = new FakeOtpRepository);
        $this->app->instance(RoleRepositoryInterface::class, $this->roles = new FakeRoleRepository);
        $this->app->instance(CartRepositoryInterface::class, $this->carts = new FakeCartRepository);
        $this->app->instance(SmsGateway::class, $this->sms = new FakeSmsGateway);
        $this->app['validator']->setPresenceVerifier(new FakePresenceVerifier);

        RateLimiter::clear('send-otp:9876543210');
        RateLimiter::clear('verify-otp:9876543210');
    }

    protected function requestOtp(string $mobile = '9876543210'): string
    {
        $this->postJson('/auth/send-otp', ['mobile' => $mobile])->assertOk();

        return $this->sms->lastCode();
    }
}
