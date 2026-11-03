<?php

namespace Tests\Feature;

use Tests\AuthTestCase;

class ProfileTest extends AuthTestCase
{
    private function customer()
    {
        return $this->users->seed(['mobile' => '9876543210', 'name' => 'User 9876543210', 'email' => null]);
    }

    public function test_profile_is_returned_in_the_api_shape(): void
    {
        $user = $this->customer();
        $user->f_name = 'Asha';
        $user->l_name = 'Shah';
        $user->email = 'asha@example.com';

        $this->actingAs($user)->getJson('/auth/profile')->assertOk()
            ->assertJson(['success' => true, 'message' => 'Profile fetched successfully', 'data' => [
                'first_name' => 'Asha', 'last_name' => 'Shah', 'email' => 'asha@example.com', 'mobile' => '9876543210',
            ]]);
    }

    public function test_profile_update_maps_names_and_keeps_name_in_step(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->postJson('/auth/update-profile', [
            'first_name' => 'Asha', 'last_name' => 'Shah', 'email' => 'asha@example.com', 'mobile' => '+91 91234 56789',
        ])->assertOk()
            ->assertJsonPath('message', 'Profile updated successfully')
            ->assertJsonPath('data.first_name', 'Asha')
            ->assertJsonPath('data.mobile', '9123456789')
            ->assertJsonPath('data.user.name', 'Asha Shah');

        $saved = $this->users->users['9123456789'];
        $this->assertSame('Asha', $saved->f_name);
        $this->assertSame('Shah', $saved->l_name);
        $this->assertSame('Asha Shah', $saved->name);
        $this->assertSame('asha@example.com', $saved->email);
    }

    public function test_email_is_left_alone_when_not_sent_and_can_be_cleared(): void
    {
        $user = $this->customer();
        $user->email = 'keep@example.com';

        $this->actingAs($user)->postJson('/auth/update-profile', ['first_name' => 'A', 'last_name' => 'B', 'mobile' => '9876543210'])->assertOk();
        $this->assertSame('keep@example.com', $user->email);

        $this->actingAs($user)->postJson('/auth/update-profile', ['first_name' => 'A', 'last_name' => 'B', 'mobile' => '9876543210', 'email' => null])->assertOk();
        $this->assertNull($user->email);
    }

    public function test_profile_validation_uses_the_api_messages_and_400(): void
    {
        $this->actingAs($this->customer())->postJson('/auth/update-profile', ['first_name' => '', 'email' => 'nope', 'mobile' => '123'])
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('data.errors.first_name.0', 'The first name field is required.')
            ->assertJsonPath('data.errors.last_name.0', 'The last name field is required.')
            ->assertJsonPath('data.errors.email.0', 'The email must be a valid email address.')
            ->assertJsonPath('data.errors.mobile.0', 'The mobile must be exactly 10 digits.');
    }
}
