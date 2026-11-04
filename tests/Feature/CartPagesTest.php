<?php

namespace Tests\Feature;

use App\Models\Wishlist;
use App\Repositories\Contracts\WishlistRepositoryInterface;
use Tests\CartTestCase;
use Tests\Support\FakeAddressRepository;
use Tests\Support\FakeCouponRepository;
use Tests\Support\FakeWishlistRepository;
use Tests\Support\Make;

require_once __DIR__.'/../Support/AccountFakes.php';

/** Phase 4 pages: the cart and the wishlist rendered by the server, their actions, and the header numbers that now come with the page. */
class CartPagesTest extends CartTestCase
{
    private FakeWishlistRepository $wish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(WishlistRepositoryInterface::class, $this->wish = new FakeWishlistRepository);
    }

    private function add(int $variantId, int $qty = 1)
    {
        return $this->postJson('/cart/add', ['product_variant_id' => $variantId, 'qty' => $qty]);
    }

    /** The id of the first line of the standard cart. */
    private function firstLine(): int
    {
        return $this->getJson('/cart/cart?delivery_type=normal')->json('data.cart.items.0.id');
    }

    // ------------------------------------------------------------------ cart page

    public function test_an_empty_cart_says_so_and_offers_the_shop(): void
    {
        $this->get('/cart')->assertOk()->assertSee('Your Cart is Currently Empty')->assertSee('Start Shopping')->assertSee('Standard Delivery')->assertSee('Quick Delivery')
            ->assertSee('id="cart-fragment"', false)->assertSee('assets/js/modules/cart.js', false)->assertDontSee('id="cart-root"', false);
    }

    public function test_the_cart_is_rendered_with_lines_forms_and_the_bill(): void
    {
        $this->sellable(1, 100);
        $this->add(1, 2)->assertOk();
        $line = $this->firstLine();

        $this->get('/cart')->assertOk()
            ->assertSee('Item 1')->assertSee('SKU: SKU1')->assertSee('₹100.00 each')->assertSee('Fresh Mart')->assertSee('₹200.00')
            ->assertSee('Subtotal (2 items)')->assertSee('Total Amount')->assertSee('Proceed to Checkout')->assertSee('/checkout', false)
            ->assertSee(route('cart.line.update', $line), false)->assertSee(route('cart.line.remove', $line), false)->assertSee(route('cart.empty'), false)
            ->assertSee('Have a coupon code?')->assertSee('name="qty" value="3"', false)->assertSee('name="qty" value="1"', false);
    }

    public function test_a_sold_out_line_is_flagged_and_the_plus_is_disabled_at_the_stock_limit(): void
    {
        $this->sellable(1, 100, 2);
        $this->add(1, 2)->assertOk();

        $html = $this->get('/cart')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<button\s+disabled[^>]*aria-label="Increase"#', $html);   // 2 of 2 in the cart

        $this->cartCatalog->free['7_1'] = 0;
        $this->catalog->stock->put('7_1', (new \App\Models\VendorProduct)->forceFill(['vendor_id' => 7, 'product_variant_id' => 1, 'stock_qty' => 0, 'reserved_qty' => 0]));
        $this->get('/cart')->assertSee('Currently unavailable - remove it to continue');
    }

    public function test_the_quick_tab_explains_quick_orders(): void
    {
        $this->get('/cart?type=30_min')->assertOk()->assertSee('No Quick Delivery items')->assertSee('Taksh app');
        $this->get('/cart?type=bogus')->assertOk()->assertSee('Your Cart is Currently Empty');   // an unknown tab is the standard cart
    }

    public function test_the_cart_opens_on_the_quick_tab_when_only_quick_has_items(): void
    {
        $this->sellable(2, 90, 5, ['is_express_30' => true, 'approval_status' => 'approved']);
        $this->postJson('/cart/add', ['product_variant_id' => 2, 'qty' => 1, 'delivery_type' => '30_min'])->assertOk();

        $this->get('/cart')->assertOk()->assertSee('Quick delivery: your order reaches you')->assertSee('30-Min')->assertSee('checkout?type=30_min', false);
        $this->get('/cart?type=normal')->assertOk()->assertSee('Your Cart is Currently Empty');   // an asked-for tab is respected
    }

    // ------------------------------------------------------------------ actions

    public function test_changing_a_quantity_comes_back_to_the_cart(): void
    {
        $this->sellable(1, 100);
        $this->add(1);
        $line = $this->firstLine();

        $this->post("/cart/lines/$line/quantity", ['qty' => 4, 'type' => 'normal'])->assertRedirect('/cart?type=normal');
        $this->assertSame(4, $this->getJson('/cart/cart?delivery_type=normal')->json('data.cart.items.0.qty'));
        $this->get('/cart')->assertSee('Subtotal (4 items)')->assertDontSee('toast(', false);   // a plain quantity change needs no message
    }

    public function test_a_quantity_of_zero_removes_the_line(): void
    {
        $this->sellable(1, 100);
        $this->add(1);

        $this->post('/cart/lines/'.$this->firstLine().'/quantity', ['qty' => 0, 'type' => 'normal'])->assertRedirect('/cart?type=normal');
        $this->assertSame([], $this->getJson('/cart/cart?delivery_type=normal')->json('data.cart.items'));
    }

    public function test_more_than_the_stock_is_refused_with_a_message(): void
    {
        $this->sellable(1, 100, 3);
        $this->add(1);

        $this->post('/cart/lines/'.$this->firstLine().'/quantity', ['qty' => 9, 'type' => 'normal'])->assertRedirect('/cart?type=normal');
        $this->get('/cart')->assertSee('toast(', false)->assertSee('"error"', false)->assertSee('id="flash-data"', false)->assertSee('Subtotal (1 items)');
    }

    public function test_a_bad_quantity_is_a_toast_not_an_error_page(): void
    {
        $this->sellable(1, 100);
        $this->add(1);
        $line = $this->firstLine();

        $this->from('/cart')->post("/cart/lines/$line/quantity", ['qty' => -2])->assertRedirect('/cart');
        $this->from('/cart')->post("/cart/lines/$line/quantity", ['qty' => 'many'])->assertRedirect('/cart');
        $this->get('/cart')->assertSee('toast(', false);
        $this->assertSame(1, $this->getJson('/cart/cart?delivery_type=normal')->json('data.cart.items.0.qty'));
    }

    public function test_removing_a_line_and_clearing_the_cart(): void
    {
        $this->sellable(1, 100);
        $this->sellable(2, 50);
        $this->add(1);
        $this->add(2);
        $first = $this->firstLine();

        $this->delete("/cart/lines/$first", ['type' => 'normal'])->assertRedirect('/cart?type=normal');
        $this->get('/cart')->assertSee('Item removed from cart');
        $this->assertCount(1, $this->getJson('/cart/cart?delivery_type=normal')->json('data.cart.items'));

        $this->delete('/cart', ['type' => 'normal'])->assertRedirect('/cart?type=normal');
        $this->get('/cart')->assertSee('Cart cleared')->assertSee('Your Cart is Currently Empty');
    }

    public function test_applying_and_removing_a_coupon(): void
    {
        $this->sellable(1, 100);
        $this->add(1, 3);
        $this->couponStore->coupons['SAVE50'] = FakeCouponRepository::coupon(['min_cart_value' => 200]);

        $this->post('/cart/coupon', ['coupon_code' => ' save50 ', 'type' => 'normal'])->assertRedirect('/cart?type=normal');
        $this->get('/cart')->assertSee('Coupon applied')->assertSee('SAVE50 applied')->assertSee('₹250.00')->assertDontSee('Have a coupon code?');

        $this->delete('/cart/coupon', ['type' => 'normal'])->assertRedirect('/cart?type=normal');
        $this->get('/cart')->assertSee('Coupon removed')->assertSee('Have a coupon code?')->assertSee('₹300.00');
    }

    public function test_a_coupon_that_does_not_apply_says_why(): void
    {
        $this->sellable(1, 100);
        $this->add(1);
        $this->couponStore->coupons['BIG'] = FakeCouponRepository::coupon(['code' => 'BIG', 'min_cart_value' => 500]);

        $this->post('/cart/coupon', ['coupon_code' => 'NOPE'])->assertRedirect('/cart?type=normal');
        $this->get('/cart')->assertSee('Invalid coupon code');

        $this->post('/cart/coupon', ['coupon_code' => 'BIG'])->assertRedirect('/cart?type=normal');
        $this->get('/cart')->assertSee('Minimum cart value of 500.00 required for this coupon');

        $this->from('/cart')->post('/cart/coupon', ['coupon_code' => ''])->assertRedirect('/cart');
        $this->get('/cart')->assertSee('Enter a coupon code');
    }

    public function test_one_visitor_cannot_change_another_visitors_cart(): void
    {
        $this->sellable(1, 100);
        $this->add(1, 2);
        $line = $this->firstLine();

        $this->flushSession();   // a different browser
        $this->post("/cart/lines/$line/quantity", ['qty' => 9, 'type' => 'normal'])->assertRedirect('/cart?type=normal');
        $this->delete("/cart/lines/$line", ['type' => 'normal'])->assertRedirect('/cart?type=normal');
        $this->get('/cart')->assertSee('Your Cart is Currently Empty');
    }

    public function test_a_customers_cart_works_the_same(): void
    {
        $this->sellable(1, 100);
        $me = $this->customer(5);
        $this->actingAs($me)->postJson('/cart/add', ['product_variant_id' => 1, 'qty' => 2])->assertOk();

        $this->actingAs($me)->get('/cart')->assertOk()->assertSee('Item 1')->assertSee('Subtotal (2 items)');
    }

    // ------------------------------------------------------------------ header numbers from the server

    public function test_the_header_numbers_are_in_the_page_itself(): void
    {
        $this->get('/')->assertSee('data-cart-count="0"', false)->assertSee('data-wish-ids="[]"', false);

        $this->sellable(1, 100);
        $this->add(1, 3);

        $this->get('/about')->assertSee('data-cart-count="3"', false)->assertSee('data-cart-total="300"', false);
    }

    public function test_the_hearts_of_saved_products_are_drawn_filled(): void
    {
        $this->wish->rows = [(new Wishlist)->forceFill(['id' => 1, 'user_id' => 5, 'product_id' => 21])];
        $this->catalog->products = collect([
            Make::product(21, ['name' => 'Saved Atta'], [Make::variant(['id' => 11])]),
            Make::product(22, ['name' => 'Other Rice'], [Make::variant(['id' => 12])]),
        ]);

        $html = $this->actingAs($this->customer(5))->get('/shop')->assertSee('data-wish-ids="[21]"', false)->getContent();

        $this->assertMatchesRegularExpression('#class="wish-btn is-on[^"]*"#', $html);
        $this->assertSame(1, preg_match_all('#class="wish-btn is-on#', $html));   // only the saved one
        $this->app['auth']->forgetGuards();
        $this->get('/shop')->assertDontSee('wish-btn is-on', false);                // a guest has no hearts
    }

    public function test_a_cart_that_cannot_be_read_does_not_break_the_page(): void
    {
        $this->app->instance(\App\Repositories\Contracts\CartRepositoryInterface::class, new class extends \Tests\Support\FakeCartRepository
        {
            public function find(?int $userId, ?string $guestToken): ?\App\Models\Cart
            {
                throw new \RuntimeException('database is down');
            }
        });

        $this->actingAs($this->customer(5))->get('/about')->assertOk()->assertSee('data-cart-count="0"', false);
    }

    // ------------------------------------------------------------------ wishlist page

    private function savedProduct(int $id, string $name, int $price = 100): void
    {
        $product = Make::product($id, ['name' => $name, 'vendor_id' => 7], [Make::variant(['id' => $id * 10, 'price' => $price + 20, 'customer_price' => $price])]);
        $this->wish->rows[] = (new Wishlist)->forceFill(['id' => $id, 'user_id' => 5, 'product_id' => $id])->setRelation('product', $product);
        $this->catalog->stock->put('7_'.($id * 10), (new \App\Models\VendorProduct)->forceFill(['vendor_id' => 7, 'product_variant_id' => $id * 10, 'stock_qty' => 5, 'reserved_qty' => 0]));
    }

    public function test_a_guest_is_asked_to_log_in_to_see_the_wishlist(): void
    {
        $this->get('/wishlist')->assertOk()->assertSee('Login to see your wishlist')->assertSee('/login?next=%2Fwishlist', false)->assertDontSee('saved item(s)');
    }

    public function test_the_wishlist_lists_the_saved_products(): void
    {
        $this->savedProduct(1, 'Saved Atta', 180);
        $this->savedProduct(2, 'Saved Rice', 90);

        $this->actingAs($this->customer(5))->get('/wishlist')->assertOk()
            ->assertSee('My Wishlist')->assertSee('2 saved item(s)')->assertSee('Saved Atta')->assertSee('Saved Rice')->assertSee('₹180')
            ->assertSee('/product?id=1', false)->assertSee(route('wishlist.forget', 1), false)->assertSee('data-wish-card="2"', false)
            ->assertSee('assets/js/modules/wishlist.js', false)->assertDontSee('id="wish-root"', false);
    }

    public function test_an_empty_wishlist_says_so(): void
    {
        $this->actingAs($this->customer(5))->get('/wishlist')->assertOk()->assertSee('Your wishlist is empty')->assertSee('Explore Products')->assertSee('0 saved item(s)');
    }

    public function test_removing_a_saved_product_without_javascript(): void
    {
        $this->savedProduct(1, 'Saved Atta');
        $this->savedProduct(2, 'Saved Rice');

        $this->actingAs($this->customer(5))->delete('/wishlist/1')->assertRedirect('/wishlist');
        $this->actingAs($this->customer(5))->get('/wishlist')->assertSee('Removed from wishlist')->assertSee('1 saved item(s)')->assertDontSee('Saved Atta')->assertSee('Saved Rice');

        $this->app['auth']->forgetGuards();
        $this->delete('/wishlist/2')->assertRedirect();   // a guest cannot (goes to the login)
        $this->assertCount(1, $this->wish->rows);
    }

    // ------------------------------------------------------------------ demo mode untouched
}
