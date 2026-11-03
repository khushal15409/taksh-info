<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\AuthTestCase;

class AuthFlowTest extends AuthTestCase
{
    public function test_send_otp_texts_a_random_code_and_stores_it_hashed(): void
    {
        $this->postJson('/auth/send-otp', ['mobile' => '+91 98765 43210'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'OTP sent successfully')
            ->assertJsonStructure(['data' => ['message', 'expires_at']])
            ->assertJsonMissingPath('data.otp');

        $this->assertSame('9876543210', $this->sms->sent[0][0]);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $this->sms->lastCode());
        $this->assertCount(1, $this->otps->rows);
        $this->assertNotSame($this->sms->lastCode(), $this->otps->rows[0]->otp);
        $this->assertSame('customer_login', $this->otps->rows[0]->context);
    }

    public function test_invalid_mobile_is_rejected_with_the_api_envelope(): void
    {
        $this->postJson('/auth/send-otp', ['mobile' => '12345'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'data' => ['errors' => ['mobile']]]);
    }

    public function test_send_otp_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/send-otp', ['mobile' => '9876543210'])->assertOk();
        }

        $this->postJson('/auth/send-otp', ['mobile' => '9876543210'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many OTP requests. Please try again later.');
    }

    public function test_a_code_that_could_not_be_texted_is_discarded(): void
    {
        $this->sms->fail = true;

        $this->postJson('/auth/send-otp', ['mobile' => '9876543210'])->assertStatus(503)->assertJsonPath('success', false);
        $this->assertCount(0, $this->otps->rows);
    }

    public function test_verify_with_a_wrong_code_fails(): void
    {
        $this->requestOtp();

        $this->postJson('/auth/verify-otp', ['mobile' => '9876543210', 'otp' => '0000' === $this->sms->lastCode() ? '1111' : '0000'])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid OTP');
        $this->assertGuest();
    }

    public function test_verify_without_a_sent_code_fails(): void
    {
        $this->postJson('/auth/verify-otp', ['mobile' => '9876543210', 'otp' => '1234'])->assertStatus(400);
    }

    public function test_verify_registers_a_new_customer_signs_in_and_uses_the_code_once(): void
    {
        $otp = $this->requestOtp();

        $this->withSession(['cart.guest_token' => 'guest-abc'])->postJson('/auth/verify-otp', ['mobile' => '9876543210', 'otp' => $otp])
            ->assertOk()
            ->assertJsonPath('data.user.mobile', '9876543210')
            ->assertJsonPath('data.user.name', 'User 9876543210')
            ->assertJsonPath('data.user.is_verified', true)
            ->assertJsonPath('data.user.mobile_display', '+91 98765 43210');

        $this->assertAuthenticated();
        $this->assertSame(['customer'], $this->roles->roles['9876543210']);
        $this->assertSame([[1, 'guest-abc']], $this->carts->merged);

        // the same code cannot be replayed
        $this->postJson('/auth/verify-otp', ['mobile' => '9876543210', 'otp' => $otp])->assertStatus(400);
    }

    public function test_existing_staff_keep_their_type_and_get_no_customer_role(): void
    {
        $this->users->seed(['mobile' => '9876543210', 'name' => 'Vendor Vic', 'user_type' => 'vendor', 'is_verified' => false, 'status' => 'pending']);
        $this->roles->roles['9876543210'] = ['vendor'];
        $otp = $this->requestOtp();

        $this->postJson('/auth/verify-otp', ['mobile' => '9876543210', 'otp' => $otp])->assertOk();

        $user = $this->users->users['9876543210'];
        $this->assertSame('vendor', $user->user_type);
        $this->assertTrue($user->is_verified);
        $this->assertSame('active', $user->status);
        $this->assertSame(['vendor'], $this->roles->roles['9876543210']);
    }

    public function test_an_existing_customer_without_a_type_becomes_a_customer(): void
    {
        $this->users->seed(['mobile' => '9876543210', 'name' => 'Old', 'user_type' => null, 'is_verified' => true, 'status' => 'active']);
        $otp = $this->requestOtp();

        $this->postJson('/auth/verify-otp', ['mobile' => '9876543210', 'otp' => $otp])->assertOk();

        $this->assertSame('customer', $this->users->users['9876543210']->user_type);
        $this->assertSame(['customer'], $this->roles->roles['9876543210']);
    }

    public function test_wrong_codes_are_throttled(): void
    {
        $this->requestOtp();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/verify-otp', ['mobile' => '9876543210', 'otp' => '0000'])->assertStatus(400);
        }

        $this->postJson('/auth/verify-otp', ['mobile' => '9876543210', 'otp' => '0000'])->assertStatus(429);
    }

    public function test_session_endpoint_reports_guest_and_user(): void
    {
        $this->getJson('/auth/session')->assertOk()->assertJsonPath('data.authenticated', false)->assertJsonPath('data.user', null);

        $user = $this->users->seed(['mobile' => '9876543210', 'name' => 'Asha Shah', 'f_name' => 'Asha', 'l_name' => 'Shah']);
        $this->actingAs($user)->getJson('/auth/session')->assertOk()
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.user.first_name', 'Asha');
    }

    public function test_logout_signs_out(): void
    {
        $user = $this->users->seed(['mobile' => '9876543210', 'name' => 'Asha']);

        $this->actingAs($user)->postJson('/auth/logout')->assertOk()->assertJsonPath('message', 'Logged out successfully');
        $this->assertGuest();
    }

    public function test_protected_endpoints_return_the_envelope_for_guests(): void
    {
        $this->getJson('/auth/profile')->assertStatus(401)->assertJsonPath('success', false);
        $this->postJson('/auth/logout')->assertStatus(401);
    }

    public function test_guests_are_sent_to_login_from_checkout_and_come_back(): void
    {
        $this->get('/checkout')->assertRedirect(route('login', ['next' => '/checkout']));
    }

    public function test_public_pages_still_render_for_guests(): void
    {
        foreach (['/', '/profile', '/tracking', '/wallet', '/login', '/help', '/about'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/nope')->assertNotFound();
    }

    public function test_the_signed_in_user_is_handed_to_the_page(): void
    {
        $user = $this->users->seed(['mobile' => '9876543210', 'name' => 'Asha Shah', 'f_name' => 'Asha', 'l_name' => 'Shah']);

        $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();
        $this->assertStringContainsString('data-user="', $html);
        $this->assertStringContainsString('Asha Shah', $html);
        $this->assertStringContainsString('name="csrf-token"', $html);
    }
}
