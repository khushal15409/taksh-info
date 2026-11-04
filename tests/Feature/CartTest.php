<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\CartTestCase;
use Tests\Support\FakeCouponRepository;

class CartTest extends CartTestCase
{
    private function add(int $variantId, int $qty = 1, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/cart/add', ['product_variant_id' => $variantId, 'qty' => $qty] + $extra);
    }

    private function cart(string $type = 'normal'): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/cart/cart?delivery_type='.$type);
    }

    // ------------------------------------------------------------------ guest cart + add (D2, D4)

    public function test_a_guest_adds_and_the_cart_is_tied_to_their_session(): void
    {
        $this->sellable(1, 100);

        $res = $this->add(1, 2)->assertOk()
            ->assertJsonPath('message', 'Item added to cart successfully')
            ->assertJsonPath('data.cart.items.0.qty', 2)
            ->assertJsonPath('data.cart.items.0.product_name', 'Item 1')
            ->assertJsonPath('data.cart.items.0.vendor_name', 'Fresh Mart')
            ->assertJsonPath('data.cart.items.0.available_stock', 10)
            ->assertJsonPath('data.cart.items.0.in_stock', true);

        $this->assertEquals(100, $res->json('data.cart.items.0.price'));
        $this->assertEquals(200, $res->json('data.cart.total'));
        $token = $res->json('data.guest_token');
        $this->assertSame(32, strlen($token));

        // the same session reads its own cart back, with no token in the request
        $this->cart()->assertOk()->assertJsonPath('data.cart.items.0.qty', 2)->assertJsonPath('data.guest_token', $token);
        $this->assertSame($token, session('cart.guest_token'));
    }

    public function test_another_browser_never_sees_a_guest_cart(): void
    {
        $this->sellable(1);
        $this->add(1)->assertOk();

        $this->flushSession();
        $this->cart()->assertOk()->assertJsonPath('data.cart.items', [])->assertJsonPath('data.guest_token', null);
    }

    public function test_adding_again_adds_to_the_quantity(): void
    {
        $this->sellable(1, 100, 10);

        $this->add(1, 2)->assertOk();
        $this->add(1, 3)->assertOk()->assertJsonPath('data.cart.items.0.qty', 5)->assertJsonCount(1, 'data.cart.items');
    }

    public function test_a_signed_in_customer_has_a_cart_of_their_own(): void
    {
        $this->sellable(1);
        $user = $this->customer(5);

        $this->actingAs($user)->add(1)->assertOk()->assertJsonPath('data.guest_token', null);

        $this->assertNotNull($this->cartStore->forUser(5));
        $this->actingAs($user)->cart()->assertJsonPath('data.cart.items.0.qty', 1);
    }

    // ------------------------------------------------------------------ stock + availability

    public function test_asking_for_more_than_the_stock_is_refused_with_the_available_amount(): void
    {
        $this->sellable(1, 100, 3);

        $this->add(1, 4)->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient stock available')->assertJsonPath('data.available_stock', 3);

        $this->add(1, 3)->assertOk();
        $this->add(1, 1)->assertStatus(422);   // 3 in the cart + 1 > 3 in stock
    }

    public function test_unavailable_products_and_vendors_are_refused(): void
    {
        $this->sellable(1, 100, 5, [], ['status' => 'inactive']);
        $this->add(1)->assertStatus(422)->assertJsonPath('message', 'This product is not available right now');

        $this->sellable(2, 100, 5, ['status' => 'draft']);
        $this->add(2)->assertStatus(422)->assertJsonPath('message', 'This product is not available right now');

        $this->sellable(3, 100, 5, ['vendor_id' => null]);
        $this->add(3)->assertStatus(422)->assertJsonPath('message', 'Product vendor is not set');

        $this->sellable(4, 100, 5, ['vendor_id' => 9]);
        $this->add(4)->assertStatus(422)->assertJsonPath('message', 'This vendor is not available right now');

        $this->add(99)->assertStatus(422);   // an unknown variant (the exists rule needs the database; the service refuses it too)
    }

    public function test_delivery_buckets_are_kept_apart(): void
    {
        $this->sellable(1);
        $this->sellable(2, 100, 5, ['is_express_30' => true, 'approval_status' => 'approved']);

        $this->add(1, 1, ['delivery_type' => '30_min'])->assertStatus(422)
            ->assertJsonPath('data.code', 'delivery_bucket_mismatch')->assertJsonPath('data.product_delivery_type', 'normal');
        $this->add(2, 1, ['delivery_type' => 'normal'])->assertStatus(422)->assertJsonPath('data.product_delivery_type', '30_min');
        $this->add(2, 1, ['delivery_type' => '30_min'])->assertOk();                       // a quick product goes into the quick cart
        $this->add(2)->assertOk();                                                          // without a delivery type: its own cart, never the standard one
        $this->cart('normal')->assertJsonPath('data.cart.items', []);
        $this->assertSame(2, $this->cart('30_min')->json('data.cart.items.0.qty'));
    }

    public function test_a_quick_product_is_only_added_for_a_place_a_shop_serves(): void
    {
        $vendors = new \Tests\Support\FakeQuickVendors;
        $vendors->vendorRows[7] = (new \Tests\Support\FakeVendor)->forceFill(['id' => 7, 'status' => 'approved', 'shop_pincode' => '395001']);
        $this->app->instance(\App\Repositories\Contracts\QuickVendorRepositoryInterface::class, $vendors);
        $this->sellable(2, 100, 5, ['is_express_30' => true, 'approval_status' => 'approved']);
        $addAt = fn (string $place) => $this->call('POST', '/cart/add', ['product_variant_id' => 2, 'qty' => 1, 'delivery_type' => '30_min'],
            [\App\Services\Quick\QuickLocation::COOKIE => $place], [], ['HTTP_ACCEPT' => 'application/json']);

        // a place no shop serves: refused, with the reason the app gives
        $addAt('110001,,')->assertStatus(422)->assertJsonPath('data.code', 'quick_item_unavailable')->assertJsonPath('data.availability_status', 'area_unserviceable');
        $this->cart('30_min')->assertJsonPath('data.cart.items', []);

        // the shop's own pincode: added
        $addAt('395001,,')->assertOk();
        $this->assertSame(1, $this->cart('30_min')->json('data.cart.items.0.qty'));
    }

    // ------------------------------------------------------------------ read + bill (D1, D5)

    public function test_an_empty_cart_has_no_charges_and_free_delivery(): void
    {
        $this->settings->values = ['platform_fee_enabled' => '1', 'platform_fee_amount' => '5'];

        $this->cart('normal')->assertOk()
            ->assertJsonPath('message', 'Cart fetched successfully')
            ->assertJsonPath('data.cart.items', [])->assertJsonPath('data.cart.total', 0)
            ->assertJsonPath('data.cart.charges', [])->assertJsonPath('data.cart.total_with_delivery', 0)
            ->assertJsonPath('data.cart.delivery_charge.code', 'normal')->assertJsonPath('data.cart.delivery_charge.price', 0);
    }

    public function test_the_bill_adds_the_fees_admin_switched_on(): void
    {
        $this->sellable(1, 100);
        $this->sellable(2, 60);
        $this->settings->values = ['platform_fee_enabled' => '1', 'platform_fee_amount' => '5', 'quick_delivery_fee_enabled' => '1', 'quick_delivery_fee_amount' => '30'];
        $this->add(1, 2);
        $this->add(2, 1);

        $cart = $this->cart('normal')->json('data.cart');

        $this->assertEquals(260, $cart['total']);                       // 2 x 100 + 60
        $this->assertSame([['type' => 'platform', 'label' => 'Platform Fees', 'amount' => 5, 'is_discount' => false, 'sort_order' => 4]], $cart['charges']);
        $this->assertEquals(265, $cart['total_with_delivery']);
        $this->assertEquals(0, $cart['delivery_charge']['price']);
        $this->assertSame(40, $cart['delivery_charge']['estimated_minutes']);
    }

    public function test_standard_and_quick_carts_are_separate(): void
    {
        $this->sellable(1, 100);
        $this->add(1);
        // a quick line the customer put in from the app
        $quick = $this->sellable(2, 90, 5, ['is_express_30' => true]);
        $user = $this->customer(5);
        $cart = $this->cartStore->firstOrCreateForUser(5);
        $this->cartStore->addLine($cart, $quick, 1, fn () => null);
        $this->cartStore->addLine($cart, $this->cartCatalog->variants[1], 2, fn () => null);
        $this->settings->values = ['quick_delivery_fee_enabled' => '1', 'quick_delivery_fee_amount' => '30'];

        $std = $this->actingAs($user)->cart('normal')->json('data.cart');
        $qk = $this->actingAs($user)->cart('30_min')->json('data.cart');

        $this->assertCount(1, $std['items']);
        $this->assertFalse($std['items'][0]['is_express_30']);
        $this->assertSame([], $std['charges']);                          // no quick fee on the standard bill
        $this->assertCount(1, $qk['items']);
        $this->assertTrue($qk['items'][0]['is_express_30']);
        $this->assertEquals(120, $qk['total_with_delivery']);            // 90 + quick delivery fee 30
        $this->assertSame('quick_delivery', $qk['charges'][0]['type']);
    }

    public function test_a_line_that_cannot_be_ordered_any_more_is_flagged(): void
    {
        $this->sellable(1, 100, 5);
        $this->add(1);
        $this->cartCatalog->free['7_1'] = 0;
        $this->catalog->stock->put('7_1', (new \App\Models\VendorProduct)->forceFill(['stock_qty' => 0, 'reserved_qty' => 0]));

        $this->cart()->assertJsonPath('data.cart.items.0.in_stock', false)->assertJsonPath('data.cart.items.0.available_stock', 0);
    }

    public function test_the_delivery_type_is_required_to_read_a_cart(): void
    {
        $this->getJson('/cart/cart')->assertStatus(422)->assertJsonStructure(['data' => ['errors' => ['delivery_type']]]);
    }

    // ------------------------------------------------------------------ update / remove / clear (D2, D3)

    public function test_update_remove_and_ownership(): void
    {
        $this->sellable(1, 100, 4);
        $itemId = $this->add(1, 1)->json('data.cart.items.0.id');

        $this->postJson('/cart/update', ['cart_item_id' => $itemId, 'qty' => 3])->assertOk()
            ->assertJsonPath('message', 'Cart updated successfully')->assertJsonPath('data.cart.items.0.qty', 3);
        $this->postJson('/cart/update', ['cart_item_id' => $itemId, 'qty' => 9])->assertStatus(422)->assertJsonPath('data.available_stock', 4);
        $this->postJson('/cart/update', ['cart_item_id' => $itemId, 'qty' => 1])->assertOk()->assertJsonPath('data.cart.items.0.qty', 1);   // lowering is always allowed
        $this->postJson('/cart/update', ['cart_item_id' => $itemId, 'qty' => 0])->assertStatus(422);

        // someone else's line and a missing line look the same
        $this->flushSession();
        $this->postJson('/cart/update', ['cart_item_id' => $itemId, 'qty' => 2])->assertStatus(404)->assertJsonPath('message', 'Item not found in cart');
        $this->postJson('/cart/item/'.$itemId)->assertStatus(404);
        $this->actingAs($this->customer(5))->postJson('/cart/item/'.$itemId)->assertStatus(404);
    }

    public function test_removing_a_line(): void
    {
        $this->sellable(1);
        $this->sellable(2);
        $this->add(1);
        $keep = $this->add(2)->json('data.cart.items.1.id');
        $first = $this->cart()->json('data.cart.items.0.id');

        $this->postJson('/cart/item/'.$first)->assertOk()->assertJsonPath('message', 'Item removed from cart')
            ->assertJsonCount(1, 'data.cart.items')->assertJsonPath('data.cart.items.0.id', $keep);
    }

    public function test_clearing_a_bucket_or_the_whole_cart(): void
    {
        $this->sellable(1);
        $this->add(1);

        $this->deleteJson('/cart/clear?delivery_type=30_min')->assertOk();                       // nothing quick to clear
        $this->cart()->assertJsonCount(1, 'data.cart.items');

        $this->deleteJson('/cart/clear?delivery_type=normal')->assertOk()->assertJsonPath('message', 'Cart cleared')->assertJsonPath('data.cart.items', []);
        $this->flushSession();
        $this->deleteJson('/cart/clear')->assertOk()->assertJsonPath('data', []);               // no cart at all is still a success
    }

    // ------------------------------------------------------------------ coupons (D6)

    public function test_applying_and_removing_a_coupon(): void
    {
        $this->sellable(1, 100);
        $this->add(1, 3);
        $this->couponStore->coupons['SAVE50'] = FakeCouponRepository::coupon(['min_cart_value' => 200]);

        $res = $this->postJson('/cart/coupon/apply', ['coupon_code' => ' save50 ', 'delivery_type' => 'normal'])->assertOk()
            ->assertJsonPath('message', 'Coupon applied successfully')
            ->assertJsonPath('data.cart.coupon.code', 'SAVE50')->assertJsonPath('data.cart.coupon.valid', true);

        $this->assertEquals(300, $res->json('data.cart.total'));
        $this->assertEquals(250, $res->json('data.cart.total_with_delivery'));
        $this->assertSame('coupon', $res->json('data.cart.charges.0.type'));
        $this->assertEquals(-50, $res->json('data.cart.charges.0.amount'));
        $this->assertTrue($res->json('data.cart.charges.0.is_discount'));

        $this->postJson('/cart/coupon/remove', [])->assertOk()->assertJsonPath('message', 'Coupon removed')->assertJsonPath('data.cart.coupon', null)->assertJsonPath('data.cart.charges', []);
    }

    public function test_coupon_rules(): void
    {
        $this->sellable(1, 100);
        $this->postJson('/cart/coupon/apply', ['coupon_code' => 'X'])->assertStatus(400)->assertJsonPath('message', 'Cart is empty');

        $this->add(1);
        $post = fn (string $code) => $this->postJson('/cart/coupon/apply', ['coupon_code' => $code]);

        $post('NOPE')->assertStatus(400)->assertJsonPath('message', 'Invalid coupon code');

        $this->couponStore->coupons['OFF'] = FakeCouponRepository::coupon(['code' => 'OFF', 'is_active' => false]);
        $post('OFF')->assertJsonPath('message', 'This coupon is no longer active');

        $this->couponStore->coupons['BIG'] = FakeCouponRepository::coupon(['code' => 'BIG', 'min_cart_value' => 500]);
        $post('BIG')->assertJsonPath('message', 'Minimum cart value of 500.00 required for this coupon');

        $this->couponStore->coupons['OLD'] = FakeCouponRepository::coupon(['code' => 'OLD', 'end_at' => now()->subDay()]);
        $post('OLD')->assertJsonPath('message', 'This coupon has expired');

        $this->couponStore->coupons['SOON'] = FakeCouponRepository::coupon(['code' => 'SOON', 'start_at' => now()->addDay()]);
        $post('SOON')->assertJsonPath('message', 'This coupon is not active yet');

        $this->couponStore->coupons['LIM'] = FakeCouponRepository::coupon(['code' => 'LIM', 'usage_limit' => 5, 'usage_limit_per_user' => 1]);
        $this->couponStore->used = 5;
        $post('LIM')->assertJsonPath('message', 'This coupon has reached its usage limit');
        $this->couponStore->used = 1;
        $this->couponStore->usedByUser = 1;
        $this->actingAs($this->customer(5))->add(1);
        $this->actingAs($this->customer(5))->postJson('/cart/coupon/apply', ['coupon_code' => 'LIM'])->assertJsonPath('message', 'You have already used this coupon the maximum number of times');
    }

    public function test_a_percentage_coupon_is_capped_and_never_exceeds_the_total(): void
    {
        $this->sellable(1, 100);
        $this->add(1, 2);
        $this->couponStore->coupons['PCT'] = FakeCouponRepository::coupon(['code' => 'PCT', 'discount_type' => 'percentage', 'discount_value' => 50, 'max_discount_amount' => 30]);

        $this->postJson('/cart/coupon/apply', ['coupon_code' => 'PCT'])->assertOk()->assertJsonPath('data.cart.charges.0.amount', -30);
    }

    public function test_a_coupon_that_stops_being_valid_is_dropped_from_the_cart(): void
    {
        $this->sellable(1, 100);
        $item = $this->add(1, 3)->json('data.cart.items.0.id');
        $this->couponStore->coupons['SAVE50'] = FakeCouponRepository::coupon(['min_cart_value' => 250]);
        $this->postJson('/cart/coupon/apply', ['coupon_code' => 'SAVE50'])->assertOk();

        $this->postJson('/cart/update', ['cart_item_id' => $item, 'qty' => 1])->assertOk()
            ->assertJsonPath('data.cart.coupon.valid', false)
            ->assertJsonPath('data.cart.coupon.message', 'Minimum cart value of 250.00 required for this coupon')
            ->assertJsonPath('data.cart.charges', []);

        $this->assertNull(collect($this->cartStore->carts)->first()->coupon_code);   // cleared, not left to fail forever
    }

    public function test_a_coupon_only_applies_to_the_bucket_it_was_applied_on(): void
    {
        $this->sellable(1, 100);
        $this->add(1, 2);
        $this->couponStore->coupons['SAVE50'] = FakeCouponRepository::coupon();
        $this->postJson('/cart/coupon/apply', ['coupon_code' => 'SAVE50', 'delivery_type' => 'normal'])->assertOk();

        $this->assertSame('standard', collect($this->cartStore->carts)->first()->coupon_delivery_type);
        $this->cart('30_min')->assertJsonPath('data.cart.coupon', null);      // reading the other bucket leaves it alone
        $this->cart('normal')->assertJsonPath('data.cart.coupon.code', 'SAVE50');
    }

    // ------------------------------------------------------------------ guest -> login merge

    public function test_the_guest_cart_follows_the_customer_after_login(): void
    {
        $this->sellable(1);
        $token = $this->add(1)->json('data.guest_token');

        // OTP login in the same browser session
        $users = new \Tests\Support\FakeUserRepository;
        $this->app->instance(\App\Repositories\Contracts\UserRepositoryInterface::class, $users);
        $this->app->instance(\App\Repositories\Contracts\OtpRepositoryInterface::class, $otps = new \Tests\Support\FakeOtpRepository);
        $this->app->instance(\App\Repositories\Contracts\RoleRepositoryInterface::class, new \Tests\Support\FakeRoleRepository);
        $this->app->instance(\App\Services\Sms\SmsGateway::class, $sms = new \Tests\Support\FakeSmsGateway);

        $this->postJson('/auth/send-otp', ['mobile' => '9876543210'])->assertOk();
        $this->postJson('/auth/verify-otp', ['mobile' => '9876543210', 'otp' => $sms->lastCode()])->assertOk();

        $this->assertSame([[1, $token]], $this->cartStore->merged);
        $this->assertNull(session('cart.guest_token'));
    }
}
