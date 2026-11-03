<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Tests\AccountTestCase;
use Tests\Support\FakeNotificationRepository;
use Tests\Support\FakePresenceVerifier;
use Tests\Support\FakeUserRepository;

require_once __DIR__.'/../Support/NotificationFakes.php';
require_once __DIR__.'/../Support/OrderFakes.php';
require_once __DIR__.'/../Support/CartFakes.php';

/** Phase 2 pages: login, the account hub (profile, addresses) and notifications - rendered by the server. */
class AccountPagesTest extends AccountTestCase
{
    private FakeUserRepository $users;

    private FakeNotificationRepository $notes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(UserRepositoryInterface::class, $this->users = new FakeUserRepository);
        $this->app->instance(NotificationRepositoryInterface::class, $this->notes = new FakeNotificationRepository);
        $this->app['validator']->setPresenceVerifier(new FakePresenceVerifier);
    }

    private function me(): User
    {
        $this->app['auth']->forgetGuards();

        $user = $this->users->seed(['mobile' => '9876543210', 'name' => 'Asha Shah', 'email' => 'asha@example.com']);
        $user->f_name = 'Asha';
        $user->l_name = 'Shah';

        return $user;
    }

    // ------------------------------------------------------------------ login

    public function test_the_login_page_is_the_same_otp_page(): void
    {
        $this->get('/login')->assertOk()->assertSee('id="login-root"', false)->assertSee('assets/js/modules/login.js', false);
    }

    // ------------------------------------------------------------------ profile hub

    public function test_a_guest_sees_a_login_prompt_instead_of_the_account(): void
    {
        $this->get('/profile')->assertOk()->assertSee('Login to view your account')->assertSee('/login?next=%2Fprofile', false)->assertDontSee('Account Details');
    }

    public function test_the_profile_tab_is_rendered_with_the_customers_details(): void
    {
        $this->actingAs($this->me())->get('/profile')->assertOk()
            ->assertSee('Account Details')->assertSee('value="Asha"', false)->assertSee('value="Shah"', false)->assertSee('value="9876543210"', false)->assertSee('value="asha@example.com"', false)
            ->assertSee('My Orders')->assertSee('Logout')->assertSee('assets/js/modules/account.js', false)->assertDontSee('id="profile-root"', false);
    }

    public function test_an_unknown_tab_falls_back_to_the_profile(): void
    {
        $this->actingAs($this->me())->get('/profile?tab=hacked')->assertOk()->assertSee('Account Details');
    }

    public function test_orders_and_returns_tabs_are_rendered_by_their_own_tests(): void
    {
        // the lists are server-rendered now (see OrderPagesTest); here only that the tab exists and the old mount points are gone
        $this->app->instance(\App\Repositories\Contracts\OrderRepositoryInterface::class, new \Tests\Support\FakeOrderRepository(new \Tests\Support\FakeOrderDb, new \Tests\Support\FakeCheckoutRepository(new \Tests\Support\FakeOrderDb, new \Tests\Support\FakeCartRepository)));
        $this->actingAs($this->me())->get('/profile?tab=orders')->assertOk()->assertSee('My Orders')->assertDontSee('id="live-orders"', false);
    }

    public function test_saving_the_profile_stores_it_and_shows_a_toast(): void
    {
        $user = $this->me();

        $this->actingAs($user)->put('/profile', ['first_name' => 'Asha', 'last_name' => 'Patel', 'mobile' => '+91 91234 56789', 'email' => 'new@example.com'])
            ->assertRedirect('/profile?tab=profile');

        $saved = $this->users->users['9123456789'] ?? $user;
        $this->assertSame(['Asha', 'Patel', 'Asha Patel'], [$saved->f_name, $saved->l_name, $saved->name]);
        $this->get('/profile?tab=profile')->assertSee('Profile Updated Successfully');
    }

    public function test_a_bad_profile_goes_back_with_the_errors_and_what_was_typed(): void
    {
        $this->actingAs($this->me())->from('/profile?tab=profile')
            ->put('/profile', ['first_name' => '', 'last_name' => 'Patel', 'mobile' => '123', 'email' => 'nope'])
            ->assertRedirect('/profile?tab=profile')->assertSessionHasErrors(['first_name', 'mobile', 'email'], null, 'profile');

        $this->get('/profile?tab=profile')->assertSee('The mobile must be exactly 10 digits.')->assertSee('The email must be a valid email address.')->assertSee('value="Patel"', false);
    }

    public function test_changes_need_a_login(): void
    {
        $this->put('/profile', [])->assertRedirect('/login?next=%2Fprofile');
        $this->post('/profile/addresses/1/default')->assertRedirect();
        $this->post('/logout')->assertRedirect();
    }

    public function test_logging_out_ends_the_session(): void
    {
        $this->actingAs($this->me())->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    // ------------------------------------------------------------------ addresses

    public function test_the_address_tab_lists_the_saved_addresses_with_their_actions(): void
    {
        $user = $this->me();
        $this->addresses->seed($user->id, ['name' => 'Home Asha', 'is_default' => true, 'landmark' => 'Temple']);
        $this->addresses->seed($user->id, ['name' => 'Office Asha', 'type' => 'work', 'address_line_1' => '9 Tech Park']);
        $this->addresses->seed(99, ['name' => 'Someone Else']);

        $this->actingAs($user)->get('/profile?tab=addresses')->assertOk()
            ->assertSee('Home Asha')->assertSee('Office Asha')->assertDontSee('Someone Else')
            ->assertSee('12 Ring Road, Near Temple, Athwa, Surat, Gujarat - 395001')->assertSee('office')   // stored "work", shown "office"
            ->assertSee('Default')->assertSee('Set default')->assertSee('data-address-new', false)->assertSee('data-address-edit', false);
    }

    public function test_an_empty_address_book_and_a_single_address_cannot_be_deleted(): void
    {
        $user = $this->me();
        $this->actingAs($user)->get('/profile?tab=addresses')->assertSee('No saved addresses yet.');

        $this->addresses->seed($user->id, ['is_default' => true]);
        $this->actingAs($user)->get('/profile?tab=addresses')->assertDontSee('Delete');
    }

    public function test_setting_a_default_and_deleting_an_address(): void
    {
        $user = $this->me();
        $a = $this->addresses->seed($user->id, ['is_default' => true]);
        $b = $this->addresses->seed($user->id);

        $this->actingAs($user)->post("/profile/addresses/{$b->id}/default")->assertRedirect('/profile?tab=addresses');
        $this->assertTrue($this->addresses->items[$b->id]->is_default);
        $this->assertFalse($this->addresses->items[$a->id]->is_default);

        $this->actingAs($user)->delete("/profile/addresses/{$a->id}")->assertRedirect('/profile?tab=addresses');
        $this->assertArrayNotHasKey($a->id, $this->addresses->items);
        $this->get('/profile?tab=addresses')->assertSee('Address removed');
    }

    public function test_another_customers_address_cannot_be_touched(): void
    {
        $other = $this->addresses->seed(99);

        $this->actingAs($this->me())->post("/profile/addresses/{$other->id}/default")->assertRedirect('/profile?tab=addresses');
        $this->assertArrayHasKey($other->id, $this->addresses->items);
        $this->actingAs($this->me())->delete("/profile/addresses/{$other->id}")->assertRedirect('/profile?tab=addresses');
        $this->assertArrayHasKey($other->id, $this->addresses->items);
    }

    // ------------------------------------------------------------------ notifications

    public function test_a_guest_sees_a_login_prompt_on_the_notifications_page(): void
    {
        $this->get('/notifications')->assertOk()->assertSee('Log in to see your notifications')->assertSee('/login?next=%2Fnotifications', false);
        $this->get('/notifications/1')->assertRedirect();
    }

    public function test_the_inbox_is_rendered_with_read_state_and_tabs(): void
    {
        $this->notes->add(1, 'offer', ['title' => 'Diwali sale']);
        $this->notes->add(2, 'order', ['title' => 'Order shipped']);
        $this->notes->markSeen($this->notes->rows[1], 5);

        $this->actingAs($this->customer(5))->get('/notifications')->assertOk()
            ->assertSee('Notifications Hub')->assertSee('Diwali sale')->assertSee('Order shipped')->assertSee('Mark all as read')
            ->assertSee('/notifications/2', false)->assertSee('bg-orange-50/60', false)   // the unread one is highlighted
            ->assertDontSee('id="notif-list"', false);
    }

    public function test_filters_unread_only_and_the_empty_message(): void
    {
        $this->notes->add(1, 'promotion', ['title' => 'Sale']);
        $this->notes->add(2, 'delivery', ['title' => 'On the way']);
        $this->notes->markSeen($this->notes->rows[2], 5);

        $this->actingAs($this->customer(5))->get('/notifications?filter=offers')->assertSee('Sale')->assertDontSee('On the way');
        $this->actingAs($this->customer(5))->get('/notifications?filter=orders')->assertSee('On the way')->assertDontSee('>Sale<', false);
        $this->actingAs($this->customer(5))->get('/notifications?filter=unread')->assertSee('Sale')->assertDontSee('On the way');
        $this->actingAs($this->customer(5))->get('/notifications?filter=bogus')->assertOk()->assertSee('Sale')->assertSee('On the way');

        $this->notes->rows = [];
        $this->actingAs($this->customer(5))->get('/notifications')->assertSee('No notifications here yet.')->assertDontSee('Mark all as read');
    }

    public function test_opening_a_notification_shows_it_and_marks_it_read(): void
    {
        $this->notes->add(1, 'offer', ['title' => 'Diwali sale', 'message' => "Flat 20% off\nTill Sunday"]);

        $this->actingAs($this->customer(5))->get('/notifications/1')->assertOk()
            ->assertSee('Diwali sale')->assertSee('Flat 20% off')->assertSee('Shop now')->assertSee('All notifications');
        $this->assertTrue($this->notes->seen['5:1']);

        $this->actingAs($this->customer(5))->get('/notifications/99')->assertNotFound();
    }

    public function test_mark_all_as_read(): void
    {
        $this->notes->add(1);
        $this->notes->add(2);

        $this->actingAs($this->customer(5))->post('/notifications/mark-all-read')->assertRedirect('/notifications');
        $this->assertSame(0, $this->notes->unreadCount($this->customer(5)));
        $this->get('/notifications')->assertSee('All notifications marked as read');
    }

    // ------------------------------------------------------------------ demo mode is untouched
}
