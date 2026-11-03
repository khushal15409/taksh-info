<?php

namespace Tests\Feature;

use App\Models\Address;
use Tests\OrderTestCase;
use Tests\Support\FakeCouponRepository;
use Tests\Support\FakeVendor;

class CheckoutTest extends OrderTestCase
{
    // ------------------------------------------------------------------ place order (G5)

    public function test_a_cod_order_is_placed_reserves_stock_and_empties_the_cart(): void
    {
        $this->settings->values = ['platform_fee_enabled' => '1', 'platform_fee_amount' => '5'];
        $v1 = $this->sold(1, 100, 10);
        $v2 = $this->sold(2, 60, 10);
        $this->inCart($v1, 2);
        $this->inCart($v2, 1);

        $res = $this->place()->assertOk()
            ->assertJsonPath('message', 'Order placed successfully')
            ->assertJsonPath('data.order_status', 'pending')
            ->assertJsonPath('data.delivery_type', 'normal')
            ->assertJsonPath('data.is_quick', false)
            ->assertJsonPath('data.vendor.shop_name', 'Fresh Mart')
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonCount(1, 'data.placed_orders');

        $o = $this->db->orders[1];
        $this->assertSame(5, $o->user_id);
        $this->assertMatchesRegularExpression('/^ORD[A-Z0-9]{8}$/', $o->order_number);
        $this->assertEquals(265, $o->grand_total);                       // 2 x 100 + 60 + platform fee 5
        $this->assertEquals(5, $o->platform_fee);
        $this->assertEquals(0, $o->discount_total);
        $this->assertSame('fm', $o->order_type);
        $this->assertEquals([['type' => 'platform', 'label' => 'Platform Fees', 'amount' => 5, 'is_discount' => false]], $this->db->charges[1]);

        $payment = $this->db->payments[1];
        $this->assertSame(['cod', 26500, 'pending', 'cod'], [$payment->gateway, $payment->amount, $payment->status, $payment->method]);

        $this->assertSame(2, $this->db->stock['7_1']->reserved_qty);       // reserved, not yet sold
        $this->assertSame(10, $this->db->stock['7_1']->stock_qty);
        $this->assertSame(1, $this->db->stock['7_2']->reserved_qty);

        $line = $this->db->items[1][0];
        $this->assertSame('Item 1', $line->product_name);
        $this->assertEquals(100, $line->price);
        $this->assertNotNull($line->taksh_charge);                         // the order keeps its own copy of the money
        $this->assertNotNull($line->settlement_amount);

        $this->assertNull($this->cartStore->forUser(5));                   // the cart is gone when nothing is left
        $this->assertSame([[1]], $this->notifier->placed);
        $this->assertSame([1], collect($res->json('data.placed_orders'))->pluck('id')->all());
        $this->assertSame(3, $res->json('data.placed_orders.0.items_count'));
        $this->assertArrayNotHasKey('qr_token', $res->json('data'));
        $this->assertArrayNotHasKey('commission_amount', $res->json('data.items.0'));
    }

    public function test_an_online_order_gets_a_razorpay_payment_record(): void
    {
        $this->inCart($this->sold(1, 100), 1);

        $this->place(['payment_method' => 'online'])->assertOk()->assertJsonPath('data.payment_method', 'online');

        $this->assertSame(['razorpay', 10000, 'pending'], [$this->db->payments[1]->gateway, $this->db->payments[1]->amount, $this->db->payments[1]->status]);
    }

    public function test_a_coupon_is_applied_once_and_recorded(): void
    {
        $this->inCart($this->sold(1, 100), 3);
        $cart = $this->cartStore->forUser(5);
        $cart->coupon_code = 'SAVE50';
        $cart->coupon_delivery_type = 'standard';
        $this->couponStore->coupons['SAVE50'] = FakeCouponRepository::coupon(['id' => 9]);

        $this->place()->assertOk();

        $this->assertEquals(250, $this->db->orders[1]->grand_total);
        $this->assertEquals(50, $this->db->orders[1]->discount_total);
        $this->assertSame([[9, 5, 1, 50.0]], $this->db->couponUsages);
        $this->assertSame(['SAVE50'], $this->db->lockedCoupons);
    }

    public function test_a_basket_from_two_shops_becomes_two_orders_with_one_fee_and_a_shared_coupon(): void
    {
        $this->settings->values = ['platform_fee_enabled' => '1', 'platform_fee_amount' => '5'];
        $this->checkoutStore->vendors[8] = (new FakeVendor)->forceFill(['id' => 8, 'status' => 'approved', 'shop_name' => 'Dairy Hub']);
        $this->cartCatalog->approved = [7, 8];
        $this->inCart($this->sold(1, 100), 2);                                 // shop 7: 200
        $this->inCart($this->sold(2, 100, 10, ['vendor_id' => 8]), 1);         // shop 8: 100
        $cart = $this->cartStore->forUser(5);
        $cart->coupon_code = 'SAVE30';
        $this->couponStore->coupons['SAVE30'] = FakeCouponRepository::coupon(['id' => 3, 'code' => 'SAVE30', 'discount_value' => 30]);

        $res = $this->place(['payment_method' => 'online'])->assertOk()->assertJsonCount(2, 'data.placed_orders');

        [$a, $b] = [$this->db->orders[1], $this->db->orders[2]];
        $this->assertNotNull($a->checkout_group_id);
        $this->assertSame($a->checkout_group_id, $b->checkout_group_id);
        $this->assertEquals([5, 0], [$a->platform_fee, $b->platform_fee]);       // the fee is charged once, on the first order
        $this->assertEquals([20, 10], [$a->discount_total, $b->discount_total]); // 30 shared in proportion to 200 : 100
        $this->assertEquals(185, $a->grand_total);                               // 200 + 5 - 20
        $this->assertEquals(90, $b->grand_total);
        $this->assertSame([[3, 5, 1, 30.0]], $this->db->couponUsages);           // one use of the coupon, on the first order
        $this->assertSame(8, $res->json('data.vendor_id'));                      // the response carries the last order, like the API
    }

    public function test_online_payment_for_several_shops_needs_the_group_column(): void
    {
        $this->checkoutStore->vendors[8] = (new FakeVendor)->forceFill(['id' => 8, 'status' => 'approved']);
        $this->inCart($this->sold(1, 100), 1);
        $this->inCart($this->sold(2, 100, 10, ['vendor_id' => 8]), 1);
        $this->checkoutStore->groupColumn = false;

        $this->place(['payment_method' => 'online'])->assertStatus(422)->assertJsonPath('message', 'Your cart has products from different shops. Please pay by cash on delivery, or order one shop at a time.');
        $this->place()->assertOk()->assertJsonCount(2, 'data.placed_orders');    // cash on delivery works for any number of shops
    }

    public function test_only_the_ordered_bucket_leaves_the_cart(): void
    {
        $std = $this->sold(1, 100);
        $quick = $this->sold(2, 90, 5, ['is_express_30' => true]);
        $this->inCart($std);
        $this->inCart($quick);

        $this->place()->assertOk();

        $left = $this->cartStore->forUser(5);
        $this->assertNotNull($left);
        $this->assertCount(1, $this->cartStore->lines[$left->id]);              // the quick line stays
    }

    // ------------------------------------------------------------------ refusals

    public function test_placing_needs_a_login_and_valid_input(): void
    {
        $this->postJson('/order/place', [])->assertStatus(401);
        $this->actingAs($this->customer(5))->postJson('/order/place', [])->assertStatus(422)->assertJsonStructure(['data' => ['errors' => ['address_id', 'delivery_type', 'payment_method']]]);
        $this->place(['delivery_type' => '2_day'])->assertStatus(422);
        $this->place(['payment_method' => 'cheque'])->assertStatus(422);
    }

    public function test_refusals_leave_nothing_behind(): void
    {
        $variant = $this->sold(1, 100, 1);

        $this->place()->assertStatus(400)->assertJsonPath('message', 'Cart is empty');

        $this->inCart($variant, 2);                                              // more than the one piece in stock
        $this->place()->assertStatus(400)->assertJsonPath('message', 'Insufficient stock available');
        $this->assertSame(0, $this->db->stock['7_1']->reserved_qty);
        $this->assertSame([], $this->db->orders);

        $this->place(['address_id' => 2])->assertStatus(404);                    // not the customer's address (or no such address)
        $this->checkoutStore->addresses[2] = (new Address)->forceFill(['id' => 2, 'user_id' => 99, 'pincode' => '395001']);
        $this->place(['address_id' => 2])->assertStatus(404)->assertJsonPath('message', 'Address not found');
    }

    public function test_vendor_and_service_area_rules(): void
    {
        $this->inCart($this->sold(1, 100, 5), 1);

        $this->checkoutStore->vendors[7]->status = 'pending';
        $this->place()->assertStatus(422)->assertJsonPath('message', 'This vendor is not available right now');

        $this->checkoutStore->vendors[7]->status = 'approved';
        $this->checkoutStore->vendors[7]->serves = false;
        $this->place()->assertStatus(422)->assertJsonPath('message', 'Delivery is not available to the pincode of the selected address');
        $this->assertSame([], $this->db->orders);
    }

    public function test_a_way_of_paying_admin_switched_off_is_refused(): void
    {
        $this->inCart($this->sold(1, 100), 1);
        $this->settings->values = ['payment_option_standard_cod' => '0'];

        $this->place()->assertStatus(400)->assertJsonPath('data.errors.payment_method.0', 'This payment method is not available for this order.');
        $this->place(['payment_method' => 'online'])->assertOk();
    }

    public function test_quick_orders_are_placed_in_the_app(): void
    {
        $this->inCart($this->sold(1, 100), 1);

        $this->place(['delivery_type' => '30_min'])->assertStatus(422)->assertJsonPath('data.code', 'quick_on_app');
        $this->assertSame([], $this->db->orders);
    }

    public function test_a_line_whose_product_is_inactive_is_refused(): void
    {
        $this->inCart($this->sold(1, 100, 5, ['status' => 'draft']), 1);

        $this->place()->assertStatus(422)->assertJsonPath('message', 'This product is not available right now');
    }

    public function test_which_ways_to_pay_are_open(): void
    {
        $this->getJson('/payment-options')->assertOk()->assertJsonPath('data.standard.available', ['cod', 'online']);

        $this->settings->values = ['payment_option_standard_online' => '0', 'payment_option_quick_cod' => '0'];
        $this->getJson('/payment-options')->assertJsonPath('data.standard.available', ['cod'])->assertJsonPath('data.quick.available', ['online'])->assertJsonPath('data.standard.online', false);
    }
}
