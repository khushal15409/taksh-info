<?php

namespace Tests\Feature;

require_once __DIR__.'/../Support/QuickFakes.php';

use App\Helpers\QuickDelivery;
use App\Models\Address;
use App\Repositories\Contracts\AddressRepositoryInterface;
use App\Repositories\Contracts\QuickVendorRepositoryInterface;
use App\Services\Quick\QuickLocation;
use App\Services\Quick\QuickServiceability;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\CatalogTestCase;
use Tests\Support\FakeQuickVendors;
use Tests\Support\Make;

class QuickListingTest extends CatalogTestCase
{
    private FakeQuickVendors $vendors;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(QuickVendorRepositoryInterface::class, $this->vendors = new FakeQuickVendors);
    }

    public function test_a_shop_serves_a_place_within_ten_km_and_not_beyond(): void
    {
        // Ahmedabad centre; one shop ~3 km away, one ~25 km away, one with unusable coordinates
        $this->vendors->shops = [1 => [23.0450, 72.5714], 2 => [23.2500, 72.5714], 3 => [0.0, 0.0]];

        $this->assertSame([1], app(QuickServiceability::class)->vendorIds(23.0225, 72.5714));
        $this->assertSame([], app(QuickServiceability::class)->vendorIds(19.0760, 72.8777));   // Mumbai
        $this->assertSame([], app(QuickServiceability::class)->vendorIds(null, null, null));    // no location, no Quick
        $this->assertFalse(QuickDelivery::withinRadius(0, 0, 23.0, 72.0));
    }

    public function test_the_pincode_of_a_shop_also_counts_even_without_coordinates(): void
    {
        $this->vendors->shops = [1 => [23.0450, 72.5714]];
        $this->vendors->byPincode = ['380001' => [9]];

        $this->assertSame([9], app(QuickServiceability::class)->vendorIds(null, null, '380001'));
        $this->assertSame([1, 9], app(QuickServiceability::class)->vendorIds(23.0225, 72.5714, '380001'));   // in range first
        $this->assertSame([], app(QuickServiceability::class)->vendorIds(null, null, '000000'));
    }

    public function test_the_quick_page_only_asks_for_products_of_the_shops_that_serve_the_browsers_location(): void
    {
        $this->vendors->shops = [1 => [23.0450, 72.5714]];

        $this->withUnencryptedCookie(QuickLocation::COOKIE, '380001,23.0225,72.5714')->get('/shop?quick=1')->assertOk()
            ->assertDontSee('Quick products delivered in 30 minutes');

        $this->assertTrue($this->catalog->lastFilters['express']);
        $this->assertSame([1], $this->catalog->lastFilters['vendor_ids']);
    }

    public function test_quick_with_no_location_or_no_shop_nearby_says_so_instead_of_listing_other_products(): void
    {
        $this->get('/shop?quick=1')->assertOk()->assertSee('Choose your delivery location')->assertSee('Set location');
        $this->assertSame([], $this->catalog->lastFilters['vendor_ids']);

        $this->withUnencryptedCookie(QuickLocation::COOKIE, '110001,,')->get('/shop?quick=1')->assertOk()
            ->assertSee('Quick delivery is not available at pincode 110001')->assertSee('within 10 km');
    }

    public function test_a_pincode_typed_on_the_page_wins_and_is_remembered(): void
    {
        $this->vendors->byPincode = ['451666' => [3], '380001' => [1]];

        $res = $this->withUnencryptedCookie(QuickLocation::COOKIE, '380001,,')->get('/shop?quick=1&pincode=451666')->assertOk()
            ->assertDontSee('delivered in 30 minutes to');

        $this->assertSame([3], $this->catalog->lastFilters['vendor_ids']);
        $res->assertCookie(QuickLocation::COOKIE, '451666,,', false);

        $this->get('/shop?quick=1&pincode=abc')->assertOk();                       // not a pincode: ignored, the picked place (380001) stays
        $this->assertSame([1], $this->catalog->lastFilters['vendor_ids']);
    }

    public function test_the_header_shows_the_same_place_the_quick_page_uses(): void
    {
        $html = $this->withUnencryptedCookie(QuickLocation::COOKIE, '380001,,')->get('/shop?quick=1&pincode=451666')->assertOk()->getContent();

        $this->assertStringContainsString('data-pincode="451666"', $html);
        $this->assertStringContainsString('max-w-[130px]">451666</div>', $html);
        $this->assertStringContainsString('not available at pincode 451666', $html);   // no shop in this test serves it, but it is the same place
    }

    private function quickShop(array $counts): void
    {
        $parent = Make::category(4, ['name' => 'Grocery', 'parent_id' => null, 'delivery_types' => null]);
        $parent->setRelation('children', collect([Make::category(6, ['name' => 'Flour', 'parent_id' => 4]), Make::category(7, ['name' => 'Empty', 'parent_id' => 4])]));
        $other = Make::category(9, ['name' => 'Gadgets', 'parent_id' => null, 'delivery_types' => null]);
        $other->setRelation('children', collect());
        $this->categories->tree = collect([$parent, $other]);
        $this->categories->standardIds = [4 => [4, 6, 7], 6 => [6], 7 => [7], 9 => [9]];
        $this->vendors->byPincode = ['380001' => [1]];
        $this->catalog->quickCounts = $counts;
    }

    public function test_the_quick_filter_lists_only_categories_with_quick_products_and_ticks_what_is_shown(): void
    {
        $this->quickShop([4 => 1, 6 => 2]);

        $page = $this->get('/shop?quick=1&pincode=380001')->assertOk()->getContent();
        $html = substr($page, (int) strpos($page, 'id="filters"'), 3000);   // the footer lists categories too: look at the filter box only
        $html = substr($html, 0, (int) strpos($html, '</form>'));

        $this->assertStringContainsString('Grocery', $html);
        $this->assertStringContainsString('Flour', $html);
        $this->assertStringNotContainsString('Gadgets', $html);
        $this->assertStringNotContainsString('Empty', $html);

        $this->assertMatchesRegularExpression('/value="4"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/value="6"[^>]*checked/', $html);
        $this->assertArrayNotHasKey('category_ids', $this->catalog->lastFilters);   // nothing picked = no category filter
    }

    public function test_picking_one_quick_category_filters_and_leaves_the_others_unticked(): void
    {
        $this->quickShop([4 => 1, 6 => 2]);

        $html = $this->get('/shop?quick=1&pincode=380001&category[]=6')->assertOk()->getContent();

        $this->assertSame([6], $this->catalog->lastFilters['category_ids']);
        $this->assertMatchesRegularExpression('/value="6"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="4"[^>]*checked/', $html);
    }

    public function test_ticking_every_quick_category_is_the_same_as_no_filter(): void
    {
        $this->quickShop([4 => 1, 6 => 2]);

        $this->get('/shop?quick=1&pincode=380001&category[]=4&category[]=6')->assertOk()->assertDontSee('Grocery ✕', false);
        $this->assertArrayNotHasKey('category_ids', $this->catalog->lastFilters);
    }

    public function test_a_parent_with_some_children_picked_only_counts_those(): void
    {
        $this->quickShop([4 => 1, 6 => 2]);
        $this->categories->standardIds = [4 => [4, 6, 7], 6 => [6]];

        $this->get('/shop?pincode=380001&category[]=4')->assertOk();
        $this->assertSame([4, 6, 7], $this->catalog->lastFilters['category_ids']);       // a parent alone brings its sub-categories

        $this->get('/shop?category[]=4&category[]=6')->assertOk();
        $this->assertSame([4, 6], $this->catalog->lastFilters['category_ids']);          // its own products + the picked child only
    }

    public function test_the_saved_address_can_be_chosen_again(): void
    {
        $res = $this->withUnencryptedCookie(QuickLocation::COOKIE, '380001,,')->get('/shop?quick=1&location=saved')->assertOk();

        $this->assertSame([], $this->catalog->lastFilters['vendor_ids']);   // a guest has no saved address
        $res->assertCookieExpired(QuickLocation::COOKIE);
    }

    public function test_the_normal_shop_is_not_touched_by_a_quick_location(): void
    {
        $this->withUnencryptedCookie(QuickLocation::COOKIE, '380001,23.0225,72.5714')->get('/shop')->assertOk()->assertDontSee('Quick products delivered');
        $this->assertFalse($this->catalog->lastFilters['express']);
    }

    public function test_a_signed_in_customers_default_address_is_where_quick_delivers(): void
    {
        $addresses = new class implements AddressRepositoryInterface
        {
            public function forUser(int $userId): Collection
            {
                return collect([
                    (new Address)->forceFill(['id' => 1, 'is_default' => false, 'pincode' => '110001', 'latitude' => 28.6, 'longitude' => 77.2]),
                    (new Address)->forceFill(['id' => 2, 'is_default' => true, 'pincode' => '380001', 'latitude' => 23.0225, 'longitude' => 72.5714]),
                ]);
            }

            public function findOwned(int $userId, int $addressId): ?Address { return null; }

            public function hasAny(int $userId): bool { return true; }

            public function count(int $userId): int { return 2; }

            public function create(array $attributes): Address { return new Address; }

            public function save(Address $address, array $attributes = []): Address { return $address; }

            public function clearDefaults(int $userId): void {}

            public function latest(int $userId): ?Address { return null; }

            public function delete(Address $address): void {}

            public function fresh(Address $address): Address { return $address; }

            public function transaction(\Closure $callback): mixed { return $callback(); }
        };

        $request = Request::create('/shop');
        $request->setUserResolver(fn () => $this->customer());

        $this->assertSame(['pincode' => '380001', 'latitude' => 23.0225, 'longitude' => 72.5714], (new QuickLocation($addresses, app(\App\Repositories\Contracts\LocationRepositoryInterface::class)))->for($request));
    }
}
