<?php

namespace Tests\Feature;

use App\Models\ProductImage;
use App\Models\VendorProduct;
use App\Models\Wishlist;
use Tests\AccountTestCase;
use Tests\Support\Make;

class WishlistTest extends AccountTestCase
{
    public function test_a_wishlist_needs_a_login(): void
    {
        $this->getJson('/wishlist/items')->assertStatus(401)->assertJsonPath('success', false);
        $this->postJson('/wishlist/add', ['product_id' => 1])->assertStatus(401);
        $this->postJson('/wishlist/remove/1')->assertStatus(401);
    }

    public function test_adding_is_idempotent_and_needs_an_active_product(): void
    {
        $this->wishlist->products = [1 => Make::product(1), 2 => Make::product(2, ['status' => 'draft'])];
        $user = $this->customer(5);

        $this->actingAs($user)->postJson('/wishlist/add', ['product_id' => 1])->assertOk()->assertJsonPath('message', 'Product added to wishlist successfully');
        $this->actingAs($user)->postJson('/wishlist/add', ['product_id' => 1])->assertOk();
        $this->assertCount(1, $this->wishlist->rows);

        $this->actingAs($user)->postJson('/wishlist/add', ['product_id' => 2])->assertStatus(422)->assertJsonPath('message', 'This product is not approved.');
        $this->actingAs($user)->postJson('/wishlist/add', [])->assertStatus(422);
    }

    public function test_removing(): void
    {
        $this->wishlist->products = [1 => Make::product(1)];
        $user = $this->customer(5);
        $this->actingAs($user)->postJson('/wishlist/add', ['product_id' => 1]);

        $this->actingAs($user)->postJson('/wishlist/remove/1')->assertOk()->assertJsonPath('message', 'Product removed from wishlist');
        $this->actingAs($user)->postJson('/wishlist/remove/1')->assertStatus(404)->assertJsonPath('message', 'Product is not in your wishlist');
    }

    public function test_the_list_shows_the_price_of_the_cheapest_buyable_variant(): void
    {
        $cheapNoStock = Make::variant(['id' => 1, 'price' => 100, 'customer_price' => 90]);
        $dearInStock = Make::variant(['id' => 2, 'price' => 200, 'customer_price' => 180]);
        $product = Make::product(1, ['vendor_id' => 7, 'name' => 'Atta'], [$cheapNoStock, $dearInStock], [(new ProductImage)->forceFill(['image_url' => 'https://cdn.test/a.jpg', 'is_primary' => true])]);
        $this->catalog->stock = collect(['7_1' => (new VendorProduct)->forceFill(['stock_qty' => 2, 'reserved_qty' => 2]), '7_2' => (new VendorProduct)->forceFill(['stock_qty' => 5, 'reserved_qty' => 1])]);
        $this->wishlist->rows = [(new Wishlist)->forceFill(['id' => 9, 'user_id' => 5, 'product_id' => 1])->setRelation('product', $product)];

        $res = $this->actingAs($this->customer(5))->getJson('/wishlist/items?per_page=20')->assertOk()
            ->assertJsonPath('message', 'Wishlist fetched successfully')
            ->assertJsonPath('data.items.0.name', 'Atta')->assertJsonPath('data.items.0.wishlist_id', 9)
            ->assertJsonPath('data.items.0.thumbnail', 'https://cdn.test/a.jpg')->assertJsonPath('data.items.0.in_stock', true)
            ->assertJsonPath('data.pagination.total', 1)->assertJsonPath('data.pagination.per_page', 20);

        $this->assertEquals(180, $res->json('data.items.0.price'));        // the in-stock variant, not the cheaper sold-out one
        $this->assertEquals(200, $res->json('data.items.0.original_price'));
    }

    public function test_a_sold_out_product_is_listed_but_not_in_stock(): void
    {
        $product = Make::product(1, ['vendor_id' => 7], [Make::variant(['id' => 1, 'price' => 100, 'customer_price' => 90])]);
        $this->catalog->stock = collect();
        $this->wishlist->rows = [(new Wishlist)->forceFill(['id' => 1, 'user_id' => 5, 'product_id' => 1])->setRelation('product', $product)];

        $res = $this->actingAs($this->customer(5))->getJson('/wishlist/items')->assertOk()->assertJsonPath('data.items.0.in_stock', false);
        $this->assertEquals(90, $res->json('data.items.0.price'));          // falls back to the cheapest variant
    }

    public function test_page_size_is_validated(): void
    {
        $this->actingAs($this->customer())->getJson('/wishlist/items?per_page=500')->assertStatus(422);
    }
}
