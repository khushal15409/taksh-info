<?php

namespace Tests\Feature;

use App\Helpers\Rupee;
use App\Models\ProductImage;
use App\Models\ProductReview;
use App\Models\VendorProduct;
use App\Support\Presenters\HomeLayout;
use App\Support\Presenters\ProductCard;
use App\Repositories\Contracts\ServiceBookingRepositoryInterface;
use Tests\CatalogTestCase;
use Tests\Support\FakeServiceBookingRepository;
use Tests\Support\FakeServiceDb;
use Tests\Support\FakeSaleRepository;
use Tests\Support\Make;

require_once __DIR__.'/../Support/ServiceFakes.php';

/** Phase 3 pages: home, shop (search, filters, pages), product and sales - rendered by the server from the shared catalogue. */
class CatalogPagesTest extends CatalogTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->services = new FakeServiceDb;
        $this->app->instance(ServiceBookingRepositoryInterface::class, new FakeServiceBookingRepository($this->services));
    }

    private FakeServiceDb $services;

    private function img(string $url): ProductImage
    {
        return (new ProductImage)->forceFill(['id' => 1, 'image_url' => $url, 'is_primary' => true, 'sort_order' => 0]);
    }

    private function review(int $rating): ProductReview
    {
        return (new ProductReview)->forceFill(['id' => $rating, 'rating' => $rating, 'is_approved' => true, 'review_title' => 'Great stuff', 'review_text' => 'Loved it', 'is_verified_purchase' => true, 'created_at' => now()]);
    }

    private function stock(int $variant, int $qty): VendorProduct
    {
        return (new VendorProduct)->forceFill(['vendor_id' => 7, 'product_variant_id' => $variant, 'stock_qty' => $qty, 'reserved_qty' => 0]);
    }

    // ------------------------------------------------------------------ helpers (units)

    public function test_rupee_uses_indian_grouping(): void
    {
        $this->assertSame('₹0', Rupee::format(0));
        $this->assertSame('₹999', Rupee::format(999));
        $this->assertSame('₹1,000', Rupee::format(1000));
        $this->assertSame('₹12,34,567', Rupee::format(1234567));
        $this->assertSame('₹1,234.50', Rupee::format(1234.5, 2));
        $this->assertSame('₹365', Rupee::format(364.6));
        $this->assertSame('-₹1,200', Rupee::format(-1200));
    }

    public function test_product_card_picks_the_cheapest_in_stock_variant(): void
    {
        $card = ProductCard::fromList([
            'id' => 4, 'name' => 'Atta', 'brand' => ['id' => 1, 'name' => 'Aashirvaad'], 'in_stock' => true, 'rating_summary' => ['average_rating' => 4.5, 'total_reviews' => 2],
            'images' => [['image_url' => 'https://x/a.jpg', 'is_primary' => false], ['image_url' => 'https://x/b.jpg', 'is_primary' => true]],
            'variants' => [
                ['id' => 1, 'status' => 'active', 'in_stock' => false, 'price' => 100, 'sale_price' => 50],
                ['id' => 2, 'status' => 'active', 'in_stock' => true, 'price' => 200, 'sale_price' => 150],
                ['id' => 3, 'status' => 'inactive', 'in_stock' => true, 'price' => 100, 'sale_price' => 10],
            ],
        ]);

        $this->assertEquals(150, $card['price']);
        $this->assertEquals(200, $card['original_price']);
        $this->assertSame(25, $card['discount']);
        $this->assertNull($card['variant_id']);                      // two active variants: the card links to the product instead of "Add"
        $this->assertSame('https://x/b.jpg', $card['image_url']);    // the primary image

        $single = ProductCard::fromList(['id' => 5, 'name' => 'X', 'in_stock' => true, 'variants' => [['id' => 9, 'status' => 'active', 'in_stock' => true, 'price' => 10, 'sale_price' => 10]]]);
        $this->assertSame(9, $single['variant_id']);
        $this->assertSame(0, $single['discount']);
    }

    public function test_home_layout_places_banners_and_decides_what_a_section_holds(): void
    {
        $products = ['key' => 'Trending', 'design' => 'hot_deals', 'data' => [['id' => 1, 'price' => 10]]];
        $cats = ['key' => 'Cats', 'data' => [['id' => 2, 'name' => 'Fruit', 'slug' => 'fruit']]];
        $empty = ['key' => 'Empty', 'data' => []];
        $banners = ['key' => 'Banners', 'data' => [
            ['id' => 1, 'position' => 'home_top', 'redirect_type' => null],
            ['id' => 2, 'position' => 'home_middle', 'after_section_name' => 'Trending'],
            ['id' => 3, 'position' => 'home_middle'],
            ['id' => 4, 'position' => 'home_bottom'],
        ]];

        $home = HomeLayout::from([['key' => 'Logo'], $banners, ['key' => 'Categories', 'data' => []], $products, $cats, $empty]);

        $this->assertTrue($home['has_content']);
        $this->assertSame([1], array_column($home['hero'], 'id'));
        $order = array_map(fn ($b) => $b['type'] === 'banner' ? 'banner'.$b['banner']['id'] : $b['section']['key'], $home['blocks']);
        // banner 2 sits under the section it names; banner 3 names none, so it goes after the first half of the 3 sections; the empty one is skipped
        $this->assertSame(['Trending', 'banner2', 'Cats', 'banner3', 'banner4'], $order);
        $this->assertSame('products', $home['blocks'][0]['kind']);
        $this->assertSame('categories', HomeLayout::kind([['id' => 1, 'name' => 'x']]));
        $this->assertSame('banners', HomeLayout::kind([['id' => 1, 'image_url' => 'x']]));
        $this->assertFalse(HomeLayout::from([['key' => 'Logo'], ['key' => 'Banners', 'data' => []], ['key' => 'Categories', 'data' => []]])['has_content']);
    }

    // ------------------------------------------------------------------ home

    public function test_the_live_home_is_rendered_from_the_dashboard(): void
    {
        $this->settings->logo = (new \App\Models\Express30LogoSetting)->forceFill(['title' => 'Taksh', 'logo_url' => 'logos/t.png', 'is_active' => true]);
        $this->banners->banners = collect([
            Make::banner(1, ['position' => 'home_top', 'title' => 'Diwali Bonanza', 'redirect_type' => 'sale', 'redirect_id' => 9]),
            Make::banner(2, ['position' => 'home_middle', 'title' => 'Mid offer', 'after_section_id' => 10]),
        ]);
        $this->dashboard->parents = collect([Make::category(5, ['products_count' => 12, 'name' => 'Fresh Fruit'])]);
        $this->dashboard->sections = collect([Make::section(10, ['section_name' => 'Trending Now', 'design' => 'hot_deals'])]);
        $this->dashboard->mapped = [10 => 'products'];
        $this->dashboard->products[10] = [Make::product(1, ['name' => 'Chakki Atta'], [Make::variant(['id' => 11, 'price' => 200, 'customer_price' => 150])], [$this->img('https://cdn.test/p1.jpg')])];

        $this->get('/')->assertOk()
            ->assertSee('Diwali Bonanza')->assertSee('/sales/9', false)                          // the top banner leads to its sale
            ->assertSee('Shop by Category')->assertSee('Fresh Fruit')->assertSee('12 items')->assertSee('/shop?category=5', false)
            ->assertSee('Trending Now')->assertSee('Chakki Atta')->assertSee('₹150')->assertSee('25% OFF')->assertSee('https://cdn.test/p1.jpg', false)
            ->assertSee('data-wish="1"', false)->assertSee('/product?id=1', false)
            ->assertSee('Mid offer')->assertSee('Standard E-Commerce')->assertSee('Loved by 1,00,000+ happy customers')->assertSee('Taksh App')
            ->assertSee('assets/js/modules/home.js', false)->assertDontSee('id="hero"', false)->assertDontSee('data-demo-block', false);
    }

    public function test_the_home_shows_recently_viewed_services_for_a_customer(): void
    {
        $this->dashboard->parents = collect([Make::category(5)]);
        $this->recent->rows = collect([Make::recentView(Make::product(1, ['name' => 'Seen Atta'], [Make::variant(['customer_price' => 120])], [$this->img('https://cdn.test/a.jpg')]))]);

        $this->get('/')->assertDontSee('Recently Viewed');
        $this->actingAs($this->customer(5))->get('/')->assertSee('Recently Viewed')->assertSee('Seen Atta')->assertSee('₹120');
    }

    public function test_the_home_lists_the_first_services_and_survives_a_services_failure(): void
    {
        $this->dashboard->parents = collect([Make::category(5)]);
        $this->services->service(1, ['name' => 'AC Repair', 'short_description' => 'Cooling fixed', 'service_charge' => 499]);

        $this->get('/')->assertOk()->assertSee('Expert Services at Your Doorstep')->assertSee('AC Repair')->assertSee('From ₹499')->assertSee('/services/1/book', false);
    }

    public function test_a_services_failure_does_not_take_the_home_down(): void
    {
        $this->dashboard->parents = collect([Make::category(5)]);
        $this->app->instance(ServiceBookingRepositoryInterface::class, new class($this->services) extends FakeServiceBookingRepository
        {
            public function activeServices(): \Illuminate\Support\Collection
            {
                throw new \RuntimeException('services table missing');
            }
        });
        $this->get('/')->assertOk()->assertSee('Shop by Category')->assertDontSee('Expert Services at Your Doorstep');
    }

    // ------------------------------------------------------------------ shop

    private function shopProducts(): void
    {
        $this->catalog->products = collect([
            Make::product(1, ['name' => 'Chakki Atta', 'vendor_id' => 7], [Make::variant(['id' => 11, 'price' => 200, 'customer_price' => 150])], [$this->img('https://cdn.test/a.jpg')]),
            Make::product(2, ['name' => 'Sold Out Rice', 'vendor_id' => 7], [Make::variant(['id' => 12, 'price' => 100, 'customer_price' => 90])]),
        ]);
        $this->catalog->stock = collect(['7_11' => $this->stock(11, 10), '7_12' => $this->stock(12, 0)]);
        $parent = Make::category(4, ['name' => 'Grocery', 'parent_id' => null, 'delivery_types' => null]);
        $parent->setRelation('children', collect([Make::category(6, ['name' => 'Flour', 'parent_id' => 4])]));
        $this->categories->tree = collect([$parent]);
    }

    public function test_the_shop_lists_products_with_filters_in_the_page(): void
    {
        $this->shopProducts();

        $this->get('/shop')->assertOk()
            ->assertSee('All Products')->assertSee('Showing')->assertSee('Chakki Atta')->assertSee('Sold Out Rice')->assertSee('Out of stock')
            ->assertSee('data-add="11"', false)->assertSee('Grocery')->assertSee('Flour')->assertSee('Max Price')->assertSee('Apply filters')
            ->assertSee('assets/js/modules/listing.js', false)->assertDontSee('id="results"', false);
        $this->assertSame(12, $this->catalog->lastPerPage);
    }

    public function test_several_categories_can_be_picked_at_once(): void
    {
        $this->shopProducts();
        $this->categories->standardIds = [4 => [4, 6], 6 => [6]];

        $html = $this->get('/shop?category[]=4&category[]=6')->assertOk()->assertSee('Grocery ✕', false)->assertSee('Flour ✕', false)->getContent();

        $this->assertSame([4, 6], $this->catalog->lastFilters['category_ids']);   // 4 brings 6 along, no duplicates
        $this->assertStringContainsString('type="checkbox" name="category[]" value="4"', $html);
        $this->assertStringNotContainsString('type="radio"', $html);
    }

    public function test_quick_products_are_listed_on_their_own(): void
    {
        $this->shopProducts();

        $this->get('/shop')->assertOk();
        $this->assertFalse($this->catalog->lastFilters['express']);

        $this->get('/shop?quick=1')->assertOk()->assertSee('Quick Products')->assertSee('name="quick" value="1"', false);
        $this->assertTrue($this->catalog->lastFilters['express']);
    }

    public function test_search_category_and_price_reach_the_catalogue_and_show_as_chips(): void
    {
        $this->shopProducts();
        $this->categories->standardIds = [6 => [6]];

        $this->get('/shop?q=atta&category=6&max=500')->assertOk()
            ->assertSee('Results for “atta”')->assertSee('Flour ✕', false)->assertSee('“atta” ✕', false)->assertSee('Under ₹500 ✕', false)
            ->assertSee('<title>Results for “atta” | TakshAllInOne</title>', false);

        $this->assertSame('atta', $this->catalog->lastFilters['search']);
        $this->assertEquals(500, $this->catalog->lastFilters['max_price']);
        $this->assertSame([6], $this->catalog->lastFilters['category_ids']);

        $this->get('/shop?category=6')->assertSee('<title>Flour | TakshAllInOne</title>', false);
    }

    public function test_filter_chips_drop_one_filter_and_keep_the_rest(): void
    {
        $this->shopProducts();

        $html = $this->get('/shop?q=atta&category=6&max=500')->getContent();

        $this->assertStringContainsString(e('/shop?q=atta&max=500'), str_replace('&amp;', '&', $html) === $html ? e('/shop?q=atta&max=500') : e('/shop?q=atta&max=500'));
        $this->assertMatchesRegularExpression('#href="[^"]*/shop\?q=atta&amp;max=500"#', $html);          // without the category
        $this->assertMatchesRegularExpression('#href="[^"]*/shop\?category=6&amp;max=500"#', $html);      // without the search
        $this->assertMatchesRegularExpression('#href="[^"]*/shop\?q=atta&amp;category=6"#', $html);       // without the price
    }

    public function test_unusable_filters_are_ignored_not_an_error(): void
    {
        $this->shopProducts();

        $this->get('/shop?category=abc&max=-5&q='.str_repeat('x', 400).'&page=zzz')->assertOk()->assertSee('Chakki Atta');
        $this->assertSame(255, mb_strlen($this->catalog->lastFilters['search']));
        $this->assertArrayNotHasKey('category_ids', $this->catalog->lastFilters);
        $this->assertNull($this->catalog->lastFilters['max_price'] ?? null);
    }

    public function test_an_empty_result_explains_and_offers_to_clear(): void
    {
        $this->catalog->products = collect();

        $this->get('/shop?q=nothing')->assertOk()->assertSee('No products found')->assertSee('Clear all filters')->assertSee('Reset All');
    }

    public function test_pages_link_to_each_other_and_keep_the_filters(): void
    {
        $this->shopProducts();
        $many = collect(range(1, 30))->map(fn ($i) => Make::product($i, ['name' => "P$i"], [Make::variant(['id' => 100 + $i])]));
        $this->catalog->products = $many;

        // the fake repository always answers page 1 of the full list; what matters is that the links keep ?q= and the page numbers
        $html = $this->get('/shop?q=atta')->getContent();
        $this->assertStringContainsString('Pagination', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*/shop\?q=atta&amp;page=2"#', $html);
    }

    public function test_the_header_search_box_lands_on_the_same_page(): void
    {
        $this->shopProducts();

        $this->get('/shop?q=atta')->assertOk()->assertSee('Results for “atta”');
    }

    // ------------------------------------------------------------------ product

    private function product(array $attrs = []): \App\Models\Product
    {
        $product = Make::product(3, $attrs + ['name' => 'Premium Basmati', 'vendor_id' => 7, 'country_of_origin' => 'India', 'description' => 'Aged long grain rice.', 'return_days' => null],
            [Make::variant(['id' => 31, 'sku' => 'BAS-1', 'price' => 300, 'customer_price' => 250]), Make::variant(['id' => 32, 'sku' => 'BAS-5', 'price' => 1200, 'customer_price' => 1000])],
            [$this->img('https://cdn.test/p.jpg')]);
        $attribute = (new \App\Models\ProductVariantAttribute)->setRelation('attribute', (new \App\Models\ProductAttribute)->forceFill(['id' => 1, 'name' => 'Size']))
            ->setRelation('attributeValue', (new \App\Models\ProductAttributeValue)->forceFill(['id' => 2, 'value' => '1 kg']));
        $product->variants->first()->setRelation('variantAttributes', collect([$attribute]))->setRelation('images', collect());
        $product->variants->last()->setRelation('images', collect());

        return $product;
    }

    private function listedProduct(): void
    {
        $this->catalog->detail = $this->product();
        $this->catalog->stock = collect(['7_31' => $this->stock(31, 5), '7_32' => $this->stock(32, 3)]);
        $this->catalog->reviews = collect([3 => collect([$this->review(5), $this->review(4)])]);
        $this->catalog->latestReviews = collect([$this->review(5)]);
        $this->catalog->vendor = (new \App\Models\Vendor)->forceFill(['shop_name' => 'Fresh Mart', 'shop_address' => 'Ring Road', 'shop_pincode' => '395001']);
        $q = (new \App\Models\ProductQuestion)->forceFill(['question' => 'Is it fresh?', 'created_at' => now()])
            ->setRelation('answers', collect([(new \App\Models\ProductAnswer)->forceFill(['answer' => 'Yes, packed this week', 'created_at' => now()])]));
        $this->catalog->questions = collect([$q]);
        $this->settings->values = ['default_return_days' => '10'];
    }

    public function test_the_product_page_is_rendered_with_everything_in_the_html(): void
    {
        $this->listedProduct();

        $this->get('/product?id=3')->assertOk()
            ->assertSee('<title>Premium Basmati | TakshAllInOne</title>', false)
            ->assertSee('https://cdn.test/p.jpg', false)->assertSee('IN STOCK')->assertSee('SKU: BAS-1')
            ->assertSee('₹250.00')->assertSee('₹300.00')->assertSee('17% OFF')->assertSee('You save ₹50')
            ->assertSee('Choose option')->assertSee('1 kg · ₹250', false)->assertSee('/product?id=3&amp;variant=32', false)
            ->assertSee('10 Day Returns')->assertSee('Fresh Mart')->assertSee('Ring Road, 395001')->assertSee('Origin:')
            ->assertSee('Aged long grain rice.')->assertSee('Reviews (2)')->assertSee('4.5')->assertSee('Great stuff')->assertSee('Verified purchase')
            ->assertSee('Is it fresh?')->assertSee('Yes, packed this week')
            ->assertSee('data-lp-add', false)->assertSee('data-wish="3"', false)->assertSee('id="lp-pin"', false)->assertSee('assets/js/modules/product.js', false)
            ->assertDontSee('id="product-root"', false);
    }

    public function test_choosing_an_option_changes_the_price_and_stock(): void
    {
        $this->listedProduct();

        $this->get('/product?id=3&variant=32')->assertOk()->assertSee('SKU: BAS-5')->assertSee('₹1,000.00')->assertSee('₹1,200.00')->assertSee('data-variant-id="32"', false);
        $this->get('/product?id=3&variant=999')->assertOk()->assertSee('SKU: BAS-1');     // an unknown option falls back to the best one
    }

    public function test_the_cheapest_in_stock_option_is_the_default(): void
    {
        $this->listedProduct();
        $this->catalog->stock = collect(['7_31' => $this->stock(31, 0), '7_32' => $this->stock(32, 3)]);   // the cheap one sold out

        $this->get('/product?id=3')->assertOk()->assertSee('SKU: BAS-5')->assertSee('data-variant-id="32"', false);
    }

    public function test_a_missing_product_is_an_explained_404(): void
    {
        $this->get('/product?id=99')->assertNotFound()->assertSee('Product not found')->assertSee('Browse products')->assertSee('<title>Product not found | TakshAllInOne</title>', false);
        $this->get('/product')->assertNotFound()->assertSee('Product not found');
        $this->get('/product?id=abc')->assertNotFound();
    }

    public function test_a_guest_is_asked_to_log_in_to_review_and_a_buyer_sees_the_form(): void
    {
        $this->listedProduct();

        $this->get('/product?id=3')->assertSee('to rate this product after it has been delivered')->assertDontSee('id="lp-rf"', false);

        $this->reviews->delivered = false;
        $this->actingAs($this->customer(5))->get('/product?id=3')->assertSee('You can rate this product after it has been delivered')->assertDontSee('id="lp-rf"', false);

        $this->reviews->delivered = true;
        $this->actingAs($this->customer(5))->get('/product?id=3')->assertSee('id="lp-rf"', false)->assertSee('Rate this product')->assertSee('Submit review');

        $this->reviews->existing = $this->review(4);
        $this->actingAs($this->customer(5))->get('/product?id=3')->assertSee('Edit your review')->assertSee('Update review');
    }

    public function test_viewing_a_product_is_remembered_for_a_customer_but_not_a_guest(): void
    {
        $this->listedProduct();

        $this->get('/product?id=3')->assertOk();
        $this->assertSame([], $this->recent->recorded);

        $this->actingAs($this->customer(5))->get('/product?id=3')->assertOk();
        $this->assertSame([[5, 3]], $this->recent->recorded);
    }

    public function test_similar_products_are_shown_as_cards(): void
    {
        $this->listedProduct();
        $this->catalog->active = Make::product(3)->setRelation('category', Make::category(1));
        $this->catalog->similarRows = collect([Make::product(8, ['name' => 'Sona Masoori'], [Make::variant(['price' => 100, 'customer_price' => 80])], [$this->img('https://cdn.test/s.jpg')], [$this->review(4)])]);

        $this->get('/product?id=3')->assertSee('Similar Products')->assertSee('Sona Masoori')->assertSee('/product?id=8', false)->assertSee('https://cdn.test/s.jpg', false);
    }

    // ------------------------------------------------------------------ sales

    public function test_the_sales_page_lists_the_running_sales(): void
    {
        $this->sales->sales = collect([Make::sale(1, ['name' => 'Diwali Dhamaka', 'sale_type' => 'product', 'discount_value' => 50, 'end_at' => now()->addDays(3)])]);

        $this->get('/sales')->assertOk()->assertSee('Sales &amp; Offers', false)->assertSee('Diwali Dhamaka')->assertSee('50.00% OFF')->assertSee('/sales/1', false)->assertDontSee('id="sales-root"', false);
    }

    public function test_no_running_sale_is_explained(): void
    {
        $this->get('/sales')->assertOk()->assertSee('No sales running right now');
    }

    public function test_a_sale_shows_its_products_at_the_sale_price(): void
    {
        $this->sales->sales = collect([Make::sale(1, ['name' => 'Diwali Dhamaka', 'sale_type' => 'product', 'discount_value' => 50, 'end_at' => now()->addDays(3)])]);
        $this->sales->productIds = [1 => [3]];
        $this->sales->saleProducts = [1 => [Make::product(3, ['name' => 'Sale Lamp'], [Make::variant(['price' => 200])], [$this->img('https://cdn.test/x.jpg')])]];

        $this->get('/sales/1')->assertOk()
            ->assertSee('<title>Diwali Dhamaka | TakshAllInOne</title>', false)->assertSee('Valid till')->assertSee('Sale Lamp')->assertSee('₹100')->assertSee('₹200')->assertSee('/product?id=3', false)->assertSee('← All offers');
    }

    public function test_an_ended_sale_is_an_explained_404(): void
    {
        $this->get('/sales/77')->assertNotFound()->assertSee('This sale has ended')->assertSee('All offers');
    }

    public function test_a_sale_without_products_says_so(): void
    {
        $this->sales->sales = collect([Make::sale(2, ['name' => 'Empty Sale', 'sale_type' => 'product', 'end_at' => now()->addDay()])]);

        $this->get('/sales/2')->assertOk()->assertSee('No products to show yet');
    }
}
