<?php

namespace Tests\Feature;

use App\Repositories\Contracts\NotificationRepositoryInterface;
use Tests\CatalogTestCase;
use Tests\Support\FakeNotificationRepository;

require_once __DIR__.'/../Support/NotificationFakes.php';

class NotificationTest extends CatalogTestCase
{
    private FakeNotificationRepository $notes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(NotificationRepositoryInterface::class, $this->notes = new FakeNotificationRepository);
    }

    public function test_everything_needs_a_login(): void
    {
        $this->getJson('/notifications/items')->assertStatus(401);
        $this->getJson('/notifications/unread-count')->assertStatus(401);
        $this->postJson('/notifications/read-all')->assertStatus(401);
        $this->postJson('/notifications/1/read')->assertStatus(401);
        $this->postJson('/notifications/device-token', [])->assertStatus(401);
    }

    public function test_list_has_items_unread_count_and_pagination(): void
    {
        $this->notes->add(1, 'offer');
        $this->notes->add(2, 'order');
        $this->notes->add(3, 'weird');

        $res = $this->actingAs($this->customer(5))->getJson('/notifications/items')->assertOk()
            ->assertJsonPath('message', 'Notifications fetched successfully')
            ->assertJsonPath('data.unread_count', 3)->assertJsonPath('data.pagination.total', 3)->assertJsonPath('data.pagination.per_page', 20)
            ->assertJsonPath('data.items.0.id', 3)->assertJsonPath('data.items.0.type', 'app')   // unknown type falls back to "app"
            ->assertJsonPath('data.items.1.title', 'N2')->assertJsonPath('data.items.1.is_read', false);

        $this->assertNotNull($res->json('data.items.0.sent_at'));
    }

    public function test_filters_are_passed_through_and_validated(): void
    {
        $this->notes->add(1, 'promotion');
        $this->notes->add(2, 'delivery');

        $this->actingAs($this->customer(5))->getJson('/notifications/items?filter=offers')->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', 1);
        $this->actingAs($this->customer(5))->getJson('/notifications/items?filter=orders')->assertJsonPath('data.items.0.id', 2);
        $this->assertSame('orders', $this->notes->lastFilter);
        $this->actingAs($this->customer(5))->getJson('/notifications/items?filter=bogus')->assertStatus(422);
    }

    public function test_mark_one_read_updates_the_count_and_unknown_is_404(): void
    {
        $this->notes->add(1);
        $this->notes->add(2);

        $this->actingAs($this->customer(5))->postJson('/notifications/1/read')->assertOk()
            ->assertJsonPath('message', 'Notification marked as read')->assertJsonPath('data.unread_count', 1);
        $this->actingAs($this->customer(5))->getJson('/notifications/items?filter=unread')->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', 2);
        $this->actingAs($this->customer(5))->postJson('/notifications/99/read')->assertStatus(404)->assertJsonPath('message', 'Notification not found');
    }

    public function test_read_state_is_per_customer_and_read_all_clears_it(): void
    {
        $this->notes->add(1);
        $this->notes->add(2);

        $this->actingAs($this->customer(5))->postJson('/notifications/read-all')->assertOk()->assertJsonPath('data.unread_count', 0);
        $this->actingAs($this->customer(5))->getJson('/notifications/unread-count')->assertJsonPath('data.unread_count', 0);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer(6))->getJson('/notifications/unread-count')->assertJsonPath('data.unread_count', 2);
    }

    public function test_device_token_belongs_to_the_logged_in_customer_only(): void
    {
        $token = str_repeat('t', 40);

        $this->actingAs($this->customer(5))->postJson('/notifications/device-token', ['token' => 'short'])->assertStatus(422);
        $this->actingAs($this->customer(5))->postJson('/notifications/device-token', ['token' => $token, 'user_id' => 99])->assertOk();
        $this->assertSame(['user_id' => 5, 'platform' => 'web'], $this->notes->tokens[$token]);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer(6))->deleteJson('/notifications/device-token', ['token' => $token])->assertOk();
        $this->assertArrayHasKey($token, $this->notes->tokens);   // someone else cannot remove it

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer(5))->deleteJson('/notifications/device-token', ['token' => $token])->assertOk();
        $this->assertArrayNotHasKey($token, $this->notes->tokens);
    }
}
