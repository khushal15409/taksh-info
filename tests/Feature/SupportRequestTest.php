<?php

namespace Tests\Feature;

use App\Repositories\Contracts\SupportRequestRepositoryInterface;
use Tests\CatalogTestCase;
use Tests\Support\FakeSupportRequestRepository;

require_once __DIR__.'/../Support/SupportFakes.php';

class SupportRequestTest extends CatalogTestCase
{
    private FakeSupportRequestRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SupportRequestRepositoryInterface::class, $this->repo = new FakeSupportRequestRepository);
    }

    public function test_a_callback_needs_a_login_and_uses_the_customers_own_details(): void
    {
        $this->postJson('/request-callback')->assertStatus(401);

        $user = $this->customer(5);
        $user->name = 'Asha';
        $this->actingAs($user)->postJson('/request-callback', ['name' => 'Someone else', 'mobile' => '1111111111'])->assertOk()
            ->assertJsonPath('message', 'Callback request created successfully')
            ->assertJsonPath('data.name', 'Asha')->assertJsonPath('data.mobile', '9876543210')->assertJsonPath('data.status', 'open')->assertJsonPath('data.id', 1);
        $this->assertSame(5, $this->repo->callbacks[0]->user_id);
    }

    public function test_only_one_callback_can_be_open_at_a_time(): void
    {
        $this->actingAs($this->customer(5))->postJson('/request-callback')->assertOk();
        $this->actingAs($this->customer(5))->postJson('/request-callback')->assertStatus(400)
            ->assertJsonPath('message', 'You already have an open callback request. Please wait for it to be processed.');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer(6))->postJson('/request-callback')->assertOk();
        $this->assertCount(2, $this->repo->callbacks);
    }

    public function test_a_delivery_partner_application_is_public_and_validated(): void
    {
        $this->postJson('/delivery-boy/join-request', ['name' => 'Ravi', 'mobile' => '9123456780', 'email' => 'r@x.in', 'address' => 'Ahmedabad', 'pincode' => '380001', 'description' => 'Bike, full time'])->assertOk()
            ->assertJsonPath('message', 'Delivery boy join request submitted successfully')->assertJsonPath('data.status', 'open');
        $this->assertSame('Ravi', $this->repo->joins[0]->name);

        $this->postJson('/delivery-boy/join-request', ['name' => 'Ravi', 'mobile' => '123'])->assertStatus(400)->assertJsonPath('success', false);
        $this->postJson('/delivery-boy/join-request', ['mobile' => '9123456780'])->assertStatus(400);
        $this->postJson('/delivery-boy/join-request', ['name' => 'R', 'mobile' => '9123456780', 'email' => 'nope'])->assertStatus(400);
        $this->assertCount(1, $this->repo->joins);
    }

    public function test_a_vendor_request_needs_the_business_type_and_a_real_description(): void
    {
        $body = ['name' => 'Asha Stores', 'mobile' => '9123456780', 'email' => 'a@x.in', 'description' => 'We sell sarees and dress material', 'business_type' => 'product_vendor'];

        $this->postJson('/vendor-request', $body)->assertOk()->assertJsonPath('message', 'Vendor request submitted successfully')
            ->assertJsonPath('data.business_type', 'product_vendor')->assertJsonPath('data.email', 'a@x.in');
        $this->assertNull($this->repo->vendors[0]->user_id);
        $this->assertSame('Asha Stores', $this->repo->vendors[0]->name);

        $this->postJson('/vendor-request', ['business_type' => 'other'] + $body)->assertStatus(400);
        $this->postJson('/vendor-request', ['description' => 'short'] + $body)->assertStatus(400);
        $this->postJson('/vendor-request', ['email' => 'bad'] + $body)->assertStatus(400);
    }

    public function test_a_logged_in_customer_is_attached_and_their_own_name_is_used(): void
    {
        $user = $this->customer(5);
        $user->name = 'Asha';

        $this->actingAs($user)->postJson('/development-request', ['name' => 'Ignored', 'mobile' => '9123456780', 'email' => 'a@x.in', 'description' => 'Need a mobile app for my shop', 'request_type' => 'app_development'])
            ->assertOk()->assertJsonPath('message', 'Development request submitted successfully')->assertJsonPath('data.request_type', 'app_development');

        $this->assertSame([5, 'Asha'], [$this->repo->developments[0]->user_id, $this->repo->developments[0]->name]);
    }

    public function test_a_development_request_is_public_and_validated(): void
    {
        $ok = ['mobile' => '9123456780', 'email' => 'a@x.in', 'description' => 'Need a website for my shop', 'request_type' => 'web_development'];

        $this->postJson('/development-request', $ok)->assertOk();
        $this->assertNull($this->repo->developments[0]->user_id);

        $this->postJson('/development-request', ['request_type' => 'both'] + $ok)->assertStatus(400);
        $this->postJson('/development-request', ['mobile' => '98765'] + $ok)->assertStatus(400);
        $this->assertCount(1, $this->repo->developments);
    }

    public function test_the_form_pages_still_open(): void
    {
        foreach (['/join/delivery-partner', '/join/vendor', '/development-request', '/help'] as $path) {
            $this->get($path)->assertOk();
        }
    }
}
