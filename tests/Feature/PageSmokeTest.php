<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;
use Tests\OrderTestCase;

require_once __DIR__.'/../Support/NotificationFakes.php';
require_once __DIR__.'/../Support/AccountFakes.php';
require_once __DIR__.'/../Support/WalletFakes.php';
require_once __DIR__.'/../Support/ServiceFakes.php';
require_once __DIR__.'/../Support/CourierFakes.php';

/**
 * Every website page opens, for a guest and for a logged-in customer, with the demo UI and with the live flag.
 * When a page is added, it must be added here (the last test fails until it is).
 */
class PageSmokeTest extends OrderTestCase
{
    /** path => needs a login (a guest is sent to /login) */
    private const PAGES = [
        '/' => false, '/shop' => false, '/product' => false, '/cart' => false, '/checkout' => true, '/tracking' => false, '/profile' => false,
        '/notifications' => false, '/services' => false, '/wishlist' => false, '/privacy-policy' => false, '/order/placed' => true, '/order/pay' => true,
        '/sales' => false, '/sales/1' => false, '/terms' => false, '/login' => false, '/wallet' => false, '/help' => false, '/about' => false,
        '/refund-policy' => false, '/join/delivery-partner' => false, '/join/vendor' => false, '/development-request' => false, '/return' => false,
        '/payment/result' => false,
    ];

    /** An explaining "not found" page (404) when there is no such product / sale / return form. */
    private const NOT_FOUND = ['/product', '/sales/1'];

    /** A signed-in customer asking for something that is not there (no such order to return) gets the 404; a guest the login prompt. */
    private const NOT_FOUND_FOR_CUSTOMER = ['/return'];

    /** A signed-in customer with nothing to check out / no order to show is sent on (to the cart / the orders list). */
    private const REDIRECTS = ['/checkout', '/order/placed', '/tracking'];

    /** Web pages that need an id or a login of their own and are covered by their module's tests. */
    private const MODULE_TESTED = ['/notifications/1', '/returns/1', '/services/1/book', '/services/bookings/1', '/services/courier/1'];

    protected function setUp(): void
    {
        parent::setUp();

        // a logged-in customer's pages read their own data: give them an (empty) inbox and address book
        $this->app->instance(\App\Repositories\Contracts\NotificationRepositoryInterface::class, new \Tests\Support\FakeNotificationRepository);
        $this->app->instance(\App\Repositories\Contracts\AddressRepositoryInterface::class, new \Tests\Support\FakeAddressRepository);
        $this->app->instance(\App\Repositories\Contracts\WishlistRepositoryInterface::class, new \Tests\Support\FakeWishlistRepository);
        $this->app->instance(\App\Repositories\Contracts\ServiceBookingRepositoryInterface::class, new \Tests\Support\FakeServiceBookingRepository(new \Tests\Support\FakeServiceDb));
        $this->app->instance(\App\Repositories\Contracts\CourierBookingRepositoryInterface::class, new \Tests\Support\FakeCourierBookingRepository(new \Tests\Support\FakeCourierDb));
        $this->app->instance(\App\Repositories\Contracts\WalletRepositoryInterface::class, new \Tests\Support\FakeWalletRepository);
        $this->app->instance(\App\Repositories\Contracts\BankDetailRepositoryInterface::class, new \Tests\Support\FakeBankDetailRepository);
    }

    public static function pages(): array
    {
        $rows = [];
        foreach (self::PAGES as $path => $auth) {
            $rows[$path] = [$path, $auth];
        }

        return $rows;
    }

    /** @dataProvider pages */
    public function test_a_guest_gets_the_page_or_the_login(string $path, bool $needsLogin): void
    {
        $res = $this->get($path);

        if ($needsLogin) {
            $res->assertRedirect('/login?next='.urlencode($path));

            return;
        }

        // a guest on an order or return page is shown a login prompt (200); a missing product / sale / return is an explained 404
        in_array($path, self::NOT_FOUND, true) ? $res->assertNotFound()->assertSee('<title>', false) : $res->assertOk()->assertSee('<title>', false);
    }

    /** @dataProvider pages */
    public function test_a_logged_in_customer_gets_every_page(string $path, bool $needsLogin): void
    {
        $res = $this->actingAs($this->customer(5))->get($path);

        match (true) {
            in_array($path, self::NOT_FOUND, true) || in_array($path, self::NOT_FOUND_FOR_CUSTOMER, true) => $res->assertNotFound(),
            in_array($path, self::REDIRECTS, true) => $res->assertRedirect(),
            default => $res->assertOk(),
        };
    }

    public function test_every_page_has_the_shared_header_footer_and_scripts(): void
    {
        $html = $this->get('/about')->assertOk()->getContent();

        foreach (['id="search-form"', 'id="hdr-cart-count"', 'data-cart-count="0"', 'data-wish-ids="[]"', 'Help &amp; Legal', 'assets/js/header-state.js', 'assets/js/prefs.js'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
        foreach (['assets/js/store.js', 'assets/js/data.js', 'assets/js/products.js', 'data-live-home'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, "$gone is demo code and must be gone");
        }
    }

    public function test_every_page_route_is_covered_here(): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true) && (str_starts_with((string) $r->getActionName(), PageController::class.'@') || str_starts_with((string) $r->getActionName(), 'App\\Http\\Controllers\\Web\\')))
            ->map(fn ($r) => '/'.ltrim(preg_replace('/\{[^}]+\}/', '1', $r->uri()), '/'))
            ->map(fn ($p) => $p === '//' ? '/' : $p)
            ->unique()->sort()->values()->all();

        $covered = array_keys(self::PAGES);
        sort($covered);

        $this->assertSame([], array_values(array_diff($registered, $covered, self::MODULE_TESTED)), 'These pages are not in PageSmokeTest::PAGES.');
    }
}
