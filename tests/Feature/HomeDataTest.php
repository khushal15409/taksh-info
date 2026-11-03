<?php

namespace Tests\Feature;

use App\Models\Express30LogoSetting;
use App\Models\ProductReview;
use App\Models\User;
use Tests\HomeTestCase;
use Tests\Support\Make;

require_once __DIR__.'/../Support/ServiceFakes.php';

class HomeDataTest extends HomeTestCase
{
    private function review(int $rating): ProductReview
    {
        return (new ProductReview)->forceFill(['rating' => $rating, 'is_approved' => true]);
    }

    public function test_dashboard_is_logo_banners_categories_then_admin_sections_in_order(): void
    {
        $this->settings->logo = (new Express30LogoSetting)->forceFill(['title' => 'Taksh', 'logo_url' => 'logos/t.png', 'is_active' => true]);
        $this->banners->banners = collect([Make::banner(1)]);
        $this->dashboard->parents = collect([Make::category(5, ['products_count' => 12])]);
        $this->dashboard->sections = collect([
            Make::section(10, ['section_name' => 'Trending', 'design' => 'hot_deals', 'rows' => 2, 'items_per_row' => 3]),
            Make::section(11, ['section_name' => 'Empty one']),
        ]);
        $this->dashboard->mapped = [10 => 'products'];
        $this->dashboard->products[10] = [Make::product(1, ['description' => 'Fresh'], [Make::variant(['price' => 200, 'customer_price' => 150])], [(new \App\Models\ProductImage)->forceFill(['image_url' => 'https://cdn.test/p1.jpg'])], [$this->review(5), $this->review(4)])];

        $res = $this->getJson('/dashboard')->assertOk()->assertJsonPath('success', true)->assertJsonPath('message', 'Dashboard data loaded successfully');

        $keys = collect($res->json('data.sections'))->pluck('key')->all();
        $this->assertSame(['Logo', 'Banners', 'Categories', 'Trending', 'Empty one'], $keys);

        $res->assertJsonPath('data.sections.0.data.title', 'Taksh')
            ->assertJsonPath('data.sections.0.data.logo_url', 'https://media.test/storage/express30/logos/t.png')
            ->assertJsonPath('data.sections.1.data.0.title', 'Banner 1')
            ->assertJsonPath('data.sections.2.data.0.products_count', 12)
            ->assertJsonPath('data.sections.3.id', 10)
            ->assertJsonPath('data.sections.3.design', 'hot_deals')
            ->assertJsonPath('data.sections.3.rows', 2)
            ->assertJsonPath('data.sections.3.message', null)
            ->assertJsonPath('data.sections.4.message', 'No data mapped to this section');

        $p = $res->json('data.sections.3.data.0');
        $this->assertEquals(150.0, $p["price"]);
        $this->assertEquals(200.0, $p["original_price"]);
        $this->assertSame(25, $p['discount']);
        $this->assertSame(4.5, $p['rating_summary']['average_rating']);
        $this->assertSame(2, $p['rating_summary']['total_reviews']);
        $this->assertSame(1, $p['rating_summary']['rating_breakup']['5']);
        $this->assertTrue($p['in_stock']);
        $this->assertSame('https://cdn.test/p1.jpg', $p['image_url']);
    }

    public function test_mapped_products_win_over_categories_and_banners_and_out_of_stock_is_flagged(): void
    {
        $this->dashboard->sections = collect([Make::section(10), Make::section(11), Make::section(12)]);
        $this->dashboard->mapped = [10 => 'products', 11 => 'categories', 12 => 'banners'];
        $this->dashboard->products[10] = [Make::product(1)];
        $this->dashboard->categories[11] = [Make::category(7)];
        $this->banners->banners = collect([Make::banner(1)]);
        $this->products->stock[1] = false;

        $sections = $this->getJson('/dashboard')->json('data.sections');

        $this->assertFalse($sections[3]['data'][0]['in_stock']);
        $this->assertSame('Out of stock', $sections[3]['data'][0]['out_of_stock_message']);
        $this->assertSame('Cat 7', $sections[4]['data'][0]['name']);
        $this->assertArrayNotHasKey('products_count', $sections[4]['data'][0]);
        $this->assertSame('Banner 1', $sections[5]['data'][0]['title']);
    }

    public function test_empty_catalogue_still_answers_with_messages(): void
    {
        $sections = $this->getJson('/dashboard')->assertOk()->json('data.sections');

        $this->assertSame('No data available', $sections[1]['message']);
        $this->assertSame('No data available', $sections[2]['message']);
        $this->assertSame('Express 30', $sections[0]['data']['title']);
        $this->assertStringContainsString('assets/img/logo.png', $sections[0]['data']['logo_url']);
    }

    public function test_middle_banner_names_the_active_section_it_sits_below(): void
    {
        $after = Make::section(10, ['section_name' => 'Trending']);
        $inactive = Make::section(11, ['section_name' => 'Old', 'is_active' => false]);
        $this->banners->banners = collect([
            Make::banner(1, ['position' => 'home_middle', 'after_section_id' => 10])->setRelation('afterSection', $after),
            Make::banner(2, ['position' => 'home_middle', 'after_section_id' => 11])->setRelation('afterSection', $inactive),
        ]);

        $rows = $this->getJson('/dashboard')->json('data.sections.1.data');

        $this->assertSame([10, 'Trending'], [$rows[0]['after_section_id'], $rows[0]['after_section_name']]);
        $this->assertSame([null, null], [$rows[1]['after_section_id'], $rows[1]['after_section_name']]);
    }

    public function test_dashboard_is_cached_when_a_ttl_is_set(): void
    {
        config(['taksh.dashboard_cache_seconds' => 60]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->banners->banners = collect([Make::banner(1)]);
        $this->getJson('/dashboard')->assertJsonPath('data.sections.1.data.0.title', 'Banner 1');

        $this->banners->banners = collect([Make::banner(2)]);
        $this->getJson('/dashboard')->assertJsonPath('data.sections.1.data.0.title', 'Banner 1');   // still the cached answer
    }

    public function test_banners_filter_by_type_and_ignore_an_unknown_type(): void
    {
        $this->banners->banners = collect([Make::banner(1, ['type' => 'standard']), Make::banner(2, ['type' => 'service'])]);

        $this->getJson('/ecommerce/banners?type=service')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 2)->assertJsonPath('message', 'Banners fetched successfully');
        $this->assertSame('service', $this->banners->lastFilters['type']);

        $this->getJson('/ecommerce/banners?type=bogus')->assertJsonCount(2, 'data');
    }

    public function test_no_banners_is_a_success_with_an_empty_list(): void
    {
        $this->getJson('/ecommerce/banners')->assertOk()->assertJsonPath('data', [])->assertJsonPath('message', 'No banners found');
    }

    public function test_app_charges_are_zero_unless_switched_on(): void
    {
        $this->getJson('/app-charges')->assertOk()->assertExactJson(['success' => true, 'data' => ['platform_fee' => 0, 'quick_delivery_fee' => 0]]);

        $this->settings->values = ['platform_fee_enabled' => '1', 'platform_fee_amount' => '5.5', 'quick_delivery_fee_enabled' => '0', 'quick_delivery_fee_amount' => '30'];
        $this->getJson('/app-charges')->assertJsonPath('data.platform_fee', 5.5)->assertJsonPath('data.quick_delivery_fee', 0);
    }

    public function test_sales_list_details_and_products(): void
    {
        $this->sales->sales = collect([Make::sale(1, ['sale_type' => 'product', 'discount_value' => 50])]);
        $this->sales->productIds = [1 => [3]];
        $product = Make::product(3, [], [Make::variant(['price' => 200])], [(new \App\Models\ProductImage)->forceFill(['image_url' => 'https://cdn.test/x.jpg', 'is_primary' => true])]);
        $this->sales->saleProducts = [1 => [$product]];

        $this->getJson('/ecommerce/sales')->assertOk()
            ->assertJsonPath('data.0.discount_label', '50.00% OFF')->assertJsonPath('data.0.sale_type', 'product');

        $this->getJson('/ecommerce/sales/1')->assertOk()
            ->assertJsonPath('data.products.0.sale_price', 100)->assertJsonPath('data.products.0.original_price', 200)
            ->assertJsonPath('data.products.0.image_url', 'https://cdn.test/x.jpg');

        $this->getJson('/ecommerce/sales/1/products')->assertOk()->assertJsonPath('data.0.sale_id', 1);
        $this->getJson('/ecommerce/sales/99')->assertStatus(404)->assertJsonPath('message', 'Sale not found');
        $this->getJson('/ecommerce/sales/99/products')->assertStatus(404);
    }

    public function test_sale_products_follow_the_sale_type_and_center(): void
    {
        $this->sales->sales = collect([Make::sale(1, ['sale_type' => 'fc']), Make::sale(2, ['sale_type' => 'category'])]);
        $this->sales->fcIds = [1 => [4]];
        $this->sales->categoryIds = [2 => [1]];
        $this->sales->nearest = 4;
        $this->products->fcProducts = [4 => [Make::product(1, ['fulfillment_center_id' => 4])]];
        $this->products->inCategories = collect([Make::product(2, ['category_id' => 1]), Make::product(3, ['category_id' => 9])]);

        $this->getJson('/ecommerce/sales/1/products?latitude=23.02&longitude=72.57')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 1);
        $this->sales->nearest = null;
        $this->getJson('/ecommerce/sales/1/products')->assertJsonCount(0, 'data');
        $this->getJson('/ecommerce/sales/2/products')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 2);
    }

    public function test_recent_views_need_a_login_and_list_the_cheapest_payable_price(): void
    {
        $this->getJson('/product/recent-views')->assertStatus(401);

        $user = new User(['mobile' => '9876543210']);
        $user->id = 5;
        $this->recent->rows = collect([
            Make::recentView(Make::product(1, [], [Make::variant(['customer_price' => 300]), Make::variant(['customer_price' => 120])], [(new \App\Models\ProductImage)->forceFill(['image_url' => 'https://cdn.test/a.jpg', 'is_primary' => true])])),
            new \App\Models\RecentView,   // product was deleted
        ]);

        $this->actingAs($user)->getJson('/product/recent-views?delivery_type=express-30')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price', 120)
            ->assertJsonPath('data.0.thumbnail', 'https://cdn.test/a.jpg');

        $this->assertSame([[5, 'express_30']], $this->recent->calls);
        $this->actingAs($user)->getJson('/product/recent-views?delivery_type=junk');
        $this->assertSame([5, null], $this->recent->calls[1]);
    }
}
