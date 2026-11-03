<?php

namespace Tests\Feature;

use App\Models\ProductImage;
use App\Models\ProductReview;
use App\Models\VendorProduct;
use Tests\CatalogTestCase;
use Tests\Support\FakeVendor;
use Tests\Support\Make;

class CatalogTest extends CatalogTestCase
{
    private function img(string $url, bool $primary = true): ProductImage
    {
        return (new ProductImage)->forceFill(['id' => 1, 'image_url' => $url, 'is_primary' => $primary, 'sort_order' => 0]);
    }

    private function stock(int $vendor, int $variant, int $qty, int $reserved = 0): VendorProduct
    {
        return (new VendorProduct)->forceFill(['vendor_id' => $vendor, 'product_variant_id' => $variant, 'stock_qty' => $qty, 'reserved_qty' => $reserved]);
    }

    private function review(int $rating): ProductReview
    {
        return (new ProductReview)->forceFill(['id' => $rating, 'rating' => $rating, 'is_approved' => true, 'review_title' => 'T', 'review_text' => 'R', 'is_verified_purchase' => true, 'created_at' => now()]);
    }

    // ------------------------------------------------------------------ categories (C1)

    public function test_category_tree_follows_the_delivery_type_rules(): void
    {
        $cases = [
            [null, [null, false]],
            ['all', [null, false]],
            ['standard_delivery,express_30', [null, false]],
            ['standard_delivery', ['standard_delivery', false]],
            ['express-30', ['express_30', false]],
            ['nonsense', [null, true]],
        ];

        foreach ($cases as [$param, $expected]) {
            $this->categories->treeCalls = [];
            $this->getJson('/ecommerce/categories'.($param ? '?delivery_type='.$param : ''))->assertOk();
            $this->assertSame($expected, $this->categories->treeCalls[0], (string) $param);
        }
    }

    public function test_category_tree_payload_has_children(): void
    {
        $parent = Make::category(1, ['parent_id' => null, 'delivery_types' => null]);
        $parent->setRelation('children', collect([Make::category(2, ['parent_id' => 1])]));
        $this->categories->tree = collect([$parent]);

        $this->getJson('/ecommerce/categories')->assertOk()
            ->assertJsonPath('message', 'Categories fetched successfully')
            ->assertJsonPath('data.0.children.0.name', 'Cat 2')
            ->assertJsonPath('data.0.children.0.parent_id', 1);
    }

    public function test_parents_and_subcategories(): void
    {
        $this->categories->parentRows = collect([Make::category(1), Make::category(3)]);
        $this->getJson('/ecommerce/categories/parents')->assertOk()->assertJsonPath('data.count', 2)->assertJsonPath('data.categories.1.id', 3);

        $this->categories->parent = Make::category(1);
        $this->categories->childRows = collect([Make::category(2)]);
        $this->getJson('/ecommerce/categories/subcategories?parent_id=1')->assertOk()
            ->assertJsonPath('data.parent.name', 'Cat 1')->assertJsonPath('data.count', 1);

        $this->getJson('/ecommerce/categories/subcategories?parent_id=99')->assertStatus(400)
            ->assertJsonPath('data.errors.parent_id.0', 'The selected parent category is invalid.');
        $this->getJson('/ecommerce/categories/subcategories')->assertStatus(400)->assertJsonStructure(['data' => ['errors' => ['parent_id']]]);
    }

    // ------------------------------------------------------------------ list (C2)

    public function test_product_list_prices_stock_and_pagination(): void
    {
        $in = Make::product(1, ['vendor_id' => 7], [Make::variant(['id' => 11, 'price' => 200, 'customer_price' => 150])], [$this->img('https://cdn.test/a.jpg')]);
        $out = Make::product(2, ['vendor_id' => 7], [Make::variant(['id' => 12, 'price' => 100, 'customer_price' => 90])]);
        $this->catalog->products = collect([$in, $out]);
        $this->catalog->stock = collect(['7_11' => $this->stock(7, 11, 10, 3), '7_12' => $this->stock(7, 12, 2, 2)]);
        $this->catalog->reviews = collect([1 => collect([$this->review(5), $this->review(4)])]);

        $res = $this->getJson('/ecommerce/products?category_id=4&search=atta&min_price=10&limit=500')->assertOk()
            ->assertJsonPath('message', 'Products fetched successfully')
            ->assertJsonPath('data.total', 2)->assertJsonPath('data.per_page', 100);

        $this->assertSame(4, $this->catalog->lastFilters['category_ids'][0]);
        $this->assertSame('atta', $this->catalog->lastFilters['search']);
        $this->assertEquals(10, $this->catalog->lastFilters['min_price']);
        $this->assertSame(100, $this->catalog->lastPerPage);   // limit is capped at 100

        $a = $res->json('data.data.0');
        $this->assertEquals(200, $a['variants'][0]['price']);          // MRP
        $this->assertEquals(150, $a['variants'][0]['sale_price']);     // what the customer pays
        $this->assertSame(7, $a['variants'][0]['available_stock']);
        $this->assertTrue($a['in_stock']);
        $this->assertSame(4.5, $a['rating_summary']['average_rating']);
        $this->assertSame('https://cdn.test/a.jpg', $a['images'][0]['image_url']);
        $this->assertArrayNotHasKey('commission_amount', $a['variants'][0]);

        $b = $res->json('data.data.1');
        $this->assertFalse($b['in_stock']);
        $this->assertSame('Out of stock', $b['out_of_stock_message']);
    }

    public function test_variant_without_a_vendor_is_in_stock_while_active(): void
    {
        $this->catalog->products = collect([Make::product(1, [], [Make::variant(['id' => 1, 'status' => 'active']), Make::variant(['id' => 2, 'status' => 'inactive'])])]);

        $rows = $this->getJson('/ecommerce/products')->json('data.data.0.variants');

        $this->assertTrue($rows[0]['in_stock']);
        $this->assertFalse($rows[1]['in_stock']);
    }

    public function test_malformed_filters_are_a_400(): void
    {
        $this->getJson('/ecommerce/products?min_price=abc')->assertStatus(400)->assertJsonStructure(['data' => ['errors' => ['min_price']]]);
        $this->getJson('/ecommerce/products?limit=0')->assertStatus(400);
    }

    // ------------------------------------------------------------------ detail (C3 C4 C6)

    private function detailProduct(array $attrs = []): \App\Models\Product
    {
        $product = Make::product(3, $attrs + ['vendor_id' => 7, 'country_of_origin' => 'India', 'packer_name' => 'Pack Co', 'packer_address' => 'Surat', 'food_type' => null, 'return_days' => null],
            [Make::variant(['id' => 31, 'price' => 300, 'customer_price' => 250]), Make::variant(['id' => 32, 'price' => 200, 'customer_price' => 190])],
            [$this->img('https://cdn.test/p.jpg')]);
        $attribute = (new \App\Models\ProductVariantAttribute)->setRelation('attribute', (new \App\Models\ProductAttribute)->forceFill(['id' => 1, 'name' => 'Size']))
            ->setRelation('attributeValue', (new \App\Models\ProductAttributeValue)->forceFill(['id' => 2, 'value' => '1 kg']));
        $product->variants->first()->setRelation('variantAttributes', collect([$attribute]));
        $product->variants->first()->setRelation('images', collect());

        return $product;
    }

    public function test_product_detail_payload(): void
    {
        $this->catalog->detail = $this->detailProduct();
        $this->catalog->stock = collect(['7_31' => $this->stock(7, 31, 5), '7_32' => $this->stock(7, 32, 0)]);
        $this->catalog->reviews = collect([3 => collect([$this->review(5)])]);
        $this->catalog->latestReviews = collect([$this->review(5)]);
        $this->catalog->vendor = (new \App\Models\Vendor)->forceFill(['shop_name' => 'Fresh Mart', 'shop_address' => 'Ring Road', 'shop_pincode' => '395001']);
        $q = (new \App\Models\ProductQuestion)->forceFill(['question' => 'Is it fresh?', 'created_at' => now()])
            ->setRelation('answers', collect([(new \App\Models\ProductAnswer)->forceFill(['answer' => 'Yes', 'created_at' => now()])]));
        $this->catalog->questions = collect([$q]);
        $this->settings->values = ['default_return_days' => '10'];

        $res = $this->getJson('/ecommerce/products/3')->assertOk()->assertJsonPath('message', 'Product details fetched successfully');
        $d = $res->json('data');

        $this->assertEquals(190, $d['sale_price']);       // cheapest payable variant is the headline
        $this->assertEquals(200, $d['original_price']);
        $this->assertSame(10, $d['return_days']);
        $this->assertSame('none', $d['food_type']);
        $this->assertSame('India', $d['origin']);
        $this->assertSame('Pack Co, Surat', $d['packaging']);
        $this->assertSame('Fresh Mart', $d['vendor_name']);
        $this->assertSame('Ring Road, 395001', $d['vendor_address']);
        $this->assertSame('Size', $d['variants'][0]['variant_attributes'][0]['attribute']['name']);
        $this->assertSame('1 kg', $d['variants'][0]['variant_attributes'][0]['attribute_value']['value']);
        $this->assertSame('Is it fresh?', $d['questions_answers'][0]['question']);
        $this->assertSame('Yes', $d['questions_answers'][0]['answers'][0]['answer']);
        $this->assertSame(5, $d['reviews'][0]['rating']);
        $this->assertTrue($d['in_stock']);                 // variant 31 has free stock
        $this->assertSame([], $this->recent->recorded);    // a guest leaves no trail
    }

    public function test_viewing_a_product_is_remembered_for_a_signed_in_customer(): void
    {
        $this->catalog->detail = $this->detailProduct();

        $this->actingAs($this->customer(5))->getJson('/ecommerce/products/3')->assertOk();

        $this->assertSame([[5, 3]], $this->recent->recorded);
    }

    public function test_quick_product_detail_uses_the_campaign_sale_and_lowest_mrp(): void
    {
        $this->catalog->detail = $this->detailProduct(['is_express_30' => true]);
        $this->sales->sales = collect([Make::sale(1, ['sale_type' => 'express_30', 'discount_type' => 'flat', 'discount_value' => 20])]);
        $this->sales->expressCenters = [4];
        $this->sales->nearest = 4;

        $d = $this->getJson('/ecommerce/products/3?latitude=23.02&longitude=72.57')->assertOk()->json('data');

        $this->assertSame(1, $d['sale_id']);
        $this->assertSame('₹20.00 OFF', $d['discount_label']);
        $this->assertEquals(200, $d['original_price']);       // lowest MRP variant
    }

    public function test_unknown_product_is_a_404(): void
    {
        $this->getJson('/ecommerce/products/99')->assertStatus(404)->assertJsonPath('message', 'Product not found');
    }

    // ------------------------------------------------------------------ similar (C8) + attributes (C4)

    public function test_similar_products(): void
    {
        $this->catalog->active = Make::product(1)->setRelation('category', Make::category(1));
        $similar = Make::product(2, [], [Make::variant(['price' => 100, 'sale_price' => 80, 'customer_price' => 120])], [$this->img('https://cdn.test/s.jpg')], [$this->review(4)]);
        $this->catalog->similarRows = collect([$similar]);

        $this->getJson('/ecommerce/products/1/similar')->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.0.product_id', 2)
            ->assertJsonPath('data.0.price', 100)
            ->assertJsonPath('data.0.discount_price', 120)
            ->assertJsonPath('data.0.primary_image', 'https://cdn.test/s.jpg')
            ->assertJsonPath('data.0.rating', 4)
            ->assertJsonPath('data.0.stock_status', 'in_stock');

        $this->getJson('/ecommerce/products/50/similar')->assertStatus(404)->assertJsonPath('status', false);

        $this->catalog->active = Make::product(1)->setRelation('category', null);
        $this->getJson('/ecommerce/products/1/similar')->assertOk()->assertJsonPath('data', []);
    }

    public function test_attributes(): void
    {
        $attr = (new \App\Models\ProductAttribute)->forceFill(['id' => 1, 'name' => 'Pack Size'])
            ->setRelation('values', collect([(new \App\Models\ProductAttributeValue)->forceFill(['id' => 5, 'value' => '500 g'])]));
        $this->catalog->attributeRows = collect([$attr]);

        $this->getJson('/product/attributes')->assertOk()
            ->assertJsonPath('data.0.slug', 'pack-size')->assertJsonPath('data.0.values.0.value', '500 g');
    }

    // ------------------------------------------------------------------ search (C11) + recent searches (C12)

    private function seedSearch(): void
    {
        $this->search->matches = [['id' => 1, 'brand_name' => 'Amul'], ['id' => 2, 'brand_name' => null], ['id' => 3, 'brand_name' => null]];
        $this->search->products = collect([
            1 => Make::product(1, ['vendor_id' => 7], [Make::variant(['id' => 1, 'price' => 60, 'customer_price' => 55])], [$this->img('https://cdn.test/m.jpg')]),
            2 => Make::product(2, [], [Make::variant(['id' => 2, 'price' => 100, 'customer_price' => 99])]),
            3 => Make::product(3)->setRelation('variants', collect()),   // no active variants: skipped
        ]);
        $this->search->ratingRows = collect([1 => (object) ['average' => 4.26, 'total' => 12]]);
    }

    public function test_search_returns_priced_cards_and_skips_products_without_variants(): void
    {
        $this->seedSearch();

        $res = $this->getJson('/products/search?keyword=milk')->assertOk()
            ->assertJsonPath('status', true)->assertJsonPath('keyword', 'milk')->assertJsonPath('count', 2);

        $this->assertSame('Amul', $res->json('data.0.brand'));
        $this->assertEquals(55, $res->json('data.0.price'));
        $this->assertEquals(60, $res->json('data.0.mrp'));
        $this->assertSame(4.3, $res->json('data.0.average_rating'));
        $this->assertSame(12, $res->json('data.0.total_reviews'));
        $this->assertTrue($res->json('data.0.in_stock'));
        $this->assertSame(0, $res->json('data.1.total_reviews'));
        $this->assertSame([], $this->recentSearches->recorded);
    }

    public function test_search_only_remembers_the_keyword_for_a_signed_in_customer(): void
    {
        $this->seedSearch();

        $this->actingAs($this->customer(5))->getJson('/products/search?keyword=%20milk%20')->assertOk();

        $this->assertSame([[5, 'milk']], $this->recentSearches->recorded);
    }

    public function test_which_products_match_is_cached(): void
    {
        $this->seedSearch();
        \Illuminate\Support\Facades\Cache::flush();

        $this->getJson('/products/search?keyword=Milk')->assertOk();
        $this->getJson('/products/search?keyword=milk')->assertOk();   // same normalised keyword

        $this->assertSame(1, $this->search->matchCalls);
    }

    public function test_search_validation(): void
    {
        $this->getJson('/products/search')->assertStatus(400)->assertJsonStructure(['data' => ['errors' => ['keyword']]]);
        $this->getJson('/products/search?keyword=a&limit=500')->assertStatus(400);
    }

    public function test_recent_searches_need_a_login(): void
    {
        $this->getJson('/product/recent-searches')->assertStatus(401);

        $this->recentSearches->list = ['milk', 'atta'];
        $this->actingAs($this->customer())->getJson('/product/recent-searches')->assertOk()->assertJsonPath('data', ['milk', 'atta']);
    }

    // ------------------------------------------------------------------ delivery check (C7)

    private function checkable(array $attrs = []): FakeVendor
    {
        $this->delivery->product = Make::product(3, $attrs + ['vendor_id' => 7]);
        $this->delivery->vendor = (new FakeVendor)->forceFill(['id' => 7, 'vendor_name' => 'V', 'shop_name' => 'Shop']);

        return $this->delivery->vendor;
    }

    public function test_delivery_check_branches(): void
    {
        $post = fn (array $extra = []) => $this->postJson('/ecommerce/products/3/check-delivery', ['pincode' => '395001'] + $extra);

        $vendor = $this->checkable();
        $post(['product_variant_id' => 31, 'qty' => 2])->assertOk()
            ->assertJsonPath('data.is_deliverable', true)->assertJsonPath('data.message', 'Product is available for delivery to this pincode')
            ->assertJsonPath('data.vendor.shop_name', 'Shop')->assertJsonPath('data.delivery_type', 'normal');
        $this->assertSame([31, 2], $this->delivery->stockArgs);

        $this->delivery->stock = false;
        $post()->assertJsonPath('data.is_deliverable', false)->assertJsonPath('data.message', 'Product is out of stock');
        $this->delivery->stock = true;

        $vendor->serves = false;
        $post()->assertJsonPath('data.message', 'Pincode not found or not serviceable');
        $this->delivery->pincode = (new \App\Models\Pincode)->forceFill(['pincode' => '395001']);
        $post()->assertJsonPath('data.message', 'Product is not available for delivery to this pincode. Vendor is not mapped to this pincode.');
        $vendor->serves = true;

        $this->delivery->vendor = null;
        $post()->assertJsonPath('data.message', 'Vendor not found or not approved');

        $this->checkable(['vendor_id' => null]);
        $post()->assertJsonPath('data.message', 'Product is not assigned to any vendor');
    }

    public function test_quick_products_are_pointed_to_the_app(): void
    {
        $this->checkable(['is_express_30' => true]);

        $this->postJson('/ecommerce/products/3/check-delivery', ['pincode' => '395001'])->assertOk()
            ->assertJsonPath('data.is_deliverable', false)->assertJsonPath('data.delivery_type', '30_min');
    }

    public function test_delivery_check_validation_and_unknown_product(): void
    {
        $this->postJson('/ecommerce/products/3/check-delivery', [])->assertStatus(400)->assertJsonStructure(['data' => ['errors' => ['pincode']]]);
        $this->postJson('/ecommerce/products/3/check-delivery', ['pincode' => '395001'])->assertStatus(404)->assertJsonPath('message', 'Product not found');
    }

    // ------------------------------------------------------------------ reviews (C5)

    public function test_reviews_need_a_login(): void
    {
        $this->getJson('/reviews/pending')->assertStatus(401);
        $this->getJson('/product/3/review')->assertStatus(401);
        $this->postJson('/product/3/review', ['rating' => 5])->assertStatus(401);
    }

    public function test_review_status(): void
    {
        $this->reviews->delivered = true;
        $this->actingAs($this->customer())->getJson('/product/3/review')->assertOk()
            ->assertJsonPath('data.can_review', true)->assertJsonPath('data.has_reviewed', false)->assertJsonPath('data.review', null);

        $this->reviews->existing = $this->review(4);
        $this->actingAs($this->customer())->getJson('/product/3/review')->assertJsonPath('data.has_reviewed', true)->assertJsonPath('data.review.rating', 4);

        $this->reviews->productExists = false;
        $this->actingAs($this->customer())->getJson('/product/3/review')->assertStatus(404);
    }

    public function test_submitting_a_review_creates_then_updates(): void
    {
        $user = $this->customer(5);

        $this->actingAs($user)->postJson('/product/3/review', ['rating' => 5, 'review_title' => ' <b>Great</b> ', 'review_text' => ''])->assertStatus(201)
            ->assertJsonPath('message', 'Thank you! Your rating has been submitted')
            ->assertJsonPath('data.review.rating', 5)->assertJsonPath('data.rating_summary.total_reviews', 3);

        [$uid, $pid, $attrs] = $this->reviews->saved;
        $this->assertSame([5, 3], [$uid, $pid]);
        $this->assertSame('Great', $attrs['review_title']);          // tags stripped
        $this->assertNull($attrs['review_text']);                     // blank becomes null
        $this->assertTrue($attrs['is_verified_purchase'] && $attrs['is_approved']);

        $this->reviews->existing = $this->review(3);
        $this->actingAs($user)->postJson('/product/3/review', ['rating' => 4])->assertStatus(200)->assertJsonPath('message', 'Your rating has been updated');
    }

    public function test_only_a_delivered_purchase_can_be_rated_and_input_is_validated(): void
    {
        $this->reviews->delivered = false;
        $this->actingAs($this->customer())->postJson('/product/3/review', ['rating' => 5])->assertStatus(403)
            ->assertJsonPath('message', 'You can rate a product only after it has been delivered to you');

        $this->actingAs($this->customer())->postJson('/product/3/review', ['rating' => 9])->assertStatus(400)->assertJsonStructure(['data' => ['errors' => ['rating']]]);
    }

    public function test_pending_ratings(): void
    {
        $this->reviews->pending = collect([(object) ['order_id' => 8, 'order_number' => 'ORD8', 'delivered_at' => '2026-10-01 10:00:00', 'product_id' => 3, 'product_name' => 'Old name', 'image_url' => null], (object) ['order_id' => 9, 'order_number' => 'ORD9', 'delivered_at' => null, 'product_id' => 77, 'product_name' => 'Gone', 'image_url' => null]]);
        $this->reviews->productsById = collect([3 => Make::product(3, ['name' => 'Atta'], [], [$this->img('https://cdn.test/p.jpg')])]);

        $this->actingAs($this->customer())->getJson('/reviews/pending')->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_name', 'Atta')
            ->assertJsonPath('data.items.0.image_url', 'https://cdn.test/p.jpg')
            ->assertJsonPath('data.items.0.order_number', 'ORD8');
    }
}
