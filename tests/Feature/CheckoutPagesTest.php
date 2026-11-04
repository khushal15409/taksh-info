<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Repositories\Contracts\AddressRepositoryInterface;
use Tests\OrderTestCase;
use Tests\Support\FakeAddressRepository;

require_once __DIR__.'/../Support/AccountFakes.php';

/**
 * Phase 5 pages: checkout, order placed, pay, and the payment result - and the money rules around them:
 * a double click places one order, a payment is never taken twice, a closed window changes nothing, and the pages only say "paid" when we are paid.
 */
class CheckoutPagesTest extends OrderTestCase
{
    private const TOKEN = '3f2504e0-4f89-41d3-9a0c-0305e82c3301';

    protected function setUp(): void
    {
        parent::setUp();

        // the address book the page lists (id 1 = the address the order fakes know)
        $book = new FakeAddressRepository;
        $book->seed(5, ['is_default' => true, 'name' => 'Asha Shah']);
        $this->app->instance(AddressRepositoryInterface::class, $book);
    }

    private function me()
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($this->customer(5));
    }

    private function submit(array $extra = [])
    {
        return $this->me()->post('/checkout', $extra + ['address_id' => 1, 'payment_method' => 'cod', 'checkout_token' => self::TOKEN]);
    }

    private function cartWith(int $qty = 2, float $price = 100, int $stock = 10): void
    {
        $this->inCart($this->sold(1, $price, $stock), $qty);
    }

    // ------------------------------------------------------------------ the checkout page

    public function test_the_quick_checkout_only_lets_you_pick_an_address_the_shop_serves(): void
    {
        $vendors = new \Tests\Support\FakeQuickVendors;
        $vendors->vendorRows[7] = (new \Tests\Support\FakeVendor)->forceFill(['id' => 7, 'status' => 'approved', 'shop_pincode' => '395001']);
        $this->app->instance(\App\Repositories\Contracts\QuickVendorRepositoryInterface::class, $vendors);
        app(AddressRepositoryInterface::class)->seed(5, ['pincode' => '110001', 'name' => 'Far']);   // id 2: no shop serves it
        $this->inCart($this->sold(2, 90, 5, ['is_express_30' => true, 'approval_status' => 'approved']), 1);

        $html = $this->me()->get('/checkout?type=30_min')->assertOk()->assertSee('30-minute delivery')->assertSee('name="type" value="30_min"', false)
            ->assertSee('30-minute delivery is not available at this address.')->getContent();

        $this->assertMatchesRegularExpression('/name="address_id" value="1"(?![^>]*disabled)/', $html);
        $this->assertMatchesRegularExpression('/name="address_id" value="2"[^>]*disabled/', $html);
    }

    public function test_a_quick_order_is_placed_from_the_page_and_the_standard_cart_is_untouched(): void
    {
        $vendors = new \Tests\Support\FakeQuickVendors;
        $vendors->vendorRows[7] = (new \Tests\Support\FakeVendor)->forceFill(['id' => 7, 'status' => 'approved', 'shop_pincode' => '395001']);
        $this->app->instance(\App\Repositories\Contracts\QuickVendorRepositoryInterface::class, $vendors);
        $this->checkoutStore->vendors[7]->forceFill(['shop_pincode' => '395001']);
        $this->inCart($this->sold(2, 90, 5, ['is_express_30' => true, 'approval_status' => 'approved']), 1);
        $this->inCart($this->sold(1, 100, 5), 1);

        $this->submit(['type' => '30_min'])->assertRedirect('/order/placed?order=1');

        $this->assertTrue($this->db->orders[1]->is_express);
        $this->assertSame('30_min', $this->db->orders[1]->delivery_type);
        $left = $this->cartStore->forUser(5);
        $this->assertCount(1, $this->cartStore->lines[$left->id]);        // the standard line stays
    }

    public function test_checkout_needs_a_login_and_a_cart(): void
    {
        $this->get('/checkout')->assertRedirect('/login?next=%2Fcheckout');

        $this->me()->get('/checkout')->assertRedirect('/cart?type=normal');
        $this->get('/cart')->assertSee('Your cart is empty - add something to check out');
    }

    public function test_the_page_lists_the_address_the_ways_to_pay_and_the_bill(): void
    {
        $this->settings->values = ['platform_fee_enabled' => '1', 'platform_fee_amount' => '5'];
        $this->cartWith(2, 100);

        $this->me()->get('/checkout')->assertOk()
            ->assertSee('Review &amp; Place Order', false)->assertSee('Asha Shah')->assertSee('12 Ring Road')->assertSee('Gujarat')
            ->assertSee('name="address_id" value="1"', false)->assertSee('Cash on Delivery (COD)')->assertSee('Pay Online (Razorpay)')
            ->assertSee('Item 1')->assertSee('Qty 2 × ₹100', false)->assertSee('Platform Fees')->assertSee('Grand Total')->assertSee('₹205.00')
            ->assertSee('name="checkout_token"', false)->assertSee('Confirm Order')->assertSee('assets/js/modules/checkout.js', false)
            ->assertDontSee('id="addr-list"', false);
    }

    public function test_every_page_gets_its_own_token(): void
    {
        $this->cartWith();

        $a = $this->me()->get('/checkout')->getContent();
        $b = $this->me()->get('/checkout')->getContent();
        preg_match('/name="checkout_token" value="([0-9a-f-]{36})"/', $a, $ta);
        preg_match('/name="checkout_token" value="([0-9a-f-]{36})"/', $b, $tb);

        $this->assertNotEmpty($ta[1]);
        $this->assertNotSame($ta[1], $tb[1]);
    }

    public function test_a_way_of_paying_the_admin_switched_off_is_not_offered(): void
    {
        $this->settings->values = ['payment_option_standard_cod' => '0'];
        $this->cartWith();

        $this->me()->get('/checkout')->assertOk()->assertDontSee('Cash on Delivery (COD)')->assertSee('Pay Online (Razorpay)');

        $this->settings->values = ['payment_option_standard_cod' => '0', 'payment_option_standard_online' => '0'];
        $this->me()->get('/checkout')->assertSee('No payment method is available right now')->assertSee('disabled', false);
    }

    public function test_an_unavailable_item_blocks_confirming(): void
    {
        $this->cartWith(1, 100, 10);
        $this->cartCatalog->free['7_1'] = 0;
        $this->catalog->stock->put('7_1', (new \App\Models\VendorProduct)->forceFill(['vendor_id' => 7, 'product_variant_id' => 1, 'stock_qty' => 0, 'reserved_qty' => 0]));

        $this->me()->get('/checkout')->assertOk()->assertSee('Some items are no longer available');
        $this->submit()->assertRedirect('/cart?type=normal');
        $this->assertSame([], $this->db->orders);
    }

    // ------------------------------------------------------------------ placing an order

    public function test_a_cod_order_is_placed_and_the_customer_lands_on_the_confirmation(): void
    {
        $this->cartWith(2, 100);

        $this->submit()->assertRedirect('/order/placed?order=1');

        $this->assertCount(1, $this->db->orders);
        $this->assertSame(5, $this->db->orders[1]->user_id);
        $this->assertEquals(200, $this->db->orders[1]->grand_total);
        $this->assertNull($this->cartStore->forUser(5));                       // the cart is emptied by the order
        $this->assertSame(2, $this->db->stock['7_1']->reserved_qty);

        $this->me()->get('/order/placed?order=1')->assertOk()
            ->assertSee('Order placed successfully')->assertSee('Pay cash when your order arrives.')->assertSee('#'.$this->db->orders[1]->order_number)->assertSee('Fresh Mart')->assertSee('2 item(s)')->assertSee('₹200.00')
            ->assertSee('toast(', false)->assertSee('My Orders')->assertDontSee('Pay now');
    }

    public function test_pressing_confirm_twice_places_one_order(): void
    {
        $this->cartWith(2, 100);

        $first = $this->submit();
        $second = $this->submit();    // the same page, the same token: a double click, or a refresh of the POST

        $first->assertRedirect('/order/placed?order=1');
        $second->assertRedirect('/order/placed?order=1');
        $this->assertCount(1, $this->db->orders);
        $this->assertSame(2, $this->db->stock['7_1']->reserved_qty);           // stock held once, not twice
        $this->assertSame([[1]], $this->notifier->placed);                     // the shop was told once
        $this->assertCount(1, $this->db->payments);
    }

    public function test_the_same_token_cannot_be_used_by_another_customer(): void
    {
        $this->cartWith(2, 100);
        $this->submit()->assertRedirect('/order/placed?order=1');

        // another customer posting the same token is a different checkout (their own, empty cart)
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer(6))->post('/checkout', ['address_id' => 1, 'payment_method' => 'cod', 'checkout_token' => self::TOKEN])->assertRedirect();
        $this->assertCount(1, $this->db->orders);
    }

    public function test_a_second_checkout_with_a_new_page_and_an_empty_cart_places_nothing(): void
    {
        $this->cartWith(1, 100);
        $this->submit()->assertRedirect('/order/placed?order=1');

        $this->submit(['checkout_token' => 'aaaaaaaa-4f89-41d3-9a0c-0305e82c3301'])->assertRedirect('/cart');
        $this->get('/cart')->assertSee('Cart is empty');
        $this->assertCount(1, $this->db->orders);
    }

    public function test_a_bad_form_is_a_message_and_places_nothing(): void
    {
        $this->cartWith();

        $this->me()->from('/checkout')->post('/checkout', ['payment_method' => 'cod', 'checkout_token' => self::TOKEN])->assertRedirect('/checkout');
        $this->get('/checkout')->assertSee('Please select or add a delivery address');

        $this->me()->from('/checkout')->post('/checkout', ['address_id' => 1, 'payment_method' => 'cheque', 'checkout_token' => self::TOKEN])->assertRedirect('/checkout');
        $this->me()->from('/checkout')->post('/checkout', ['address_id' => 1, 'payment_method' => 'cod'])->assertRedirect('/checkout');            // no token
        $this->me()->from('/checkout')->post('/checkout', ['address_id' => 1, 'payment_method' => 'cod', 'checkout_token' => 'not-a-token'])->assertRedirect('/checkout');
        $this->get('/checkout')->assertSee('Your checkout page expired');
        $this->assertSame([], $this->db->orders);
        $this->assertNotNull($this->cartStore->forUser(5));                    // the cart is untouched
    }

    public function test_someone_elses_address_is_refused(): void
    {
        $this->cartWith();
        $this->checkoutStore->addresses[2] = (new Address)->forceFill(['id' => 2, 'user_id' => 99, 'pincode' => '395001', 'name' => 'Other', 'mobile' => '9000000000', 'address_line_1' => 'x', 'type' => 'home']);

        $this->submit(['address_id' => 2])->assertRedirect('/checkout');
        $this->get('/checkout')->assertSee('Address not found');
        $this->assertSame([], $this->db->orders);
        $this->assertNotNull($this->cartStore->forUser(5));
    }

    public function test_a_way_of_paying_switched_off_after_the_page_was_opened_is_refused(): void
    {
        $this->cartWith();
        $this->settings->values = ['payment_option_standard_cod' => '0'];

        $this->submit()->assertRedirect('/checkout');
        $this->get('/checkout')->assertSee('This payment method is not available for this order.');
        $this->assertSame([], $this->db->orders);
    }

    public function test_stock_that_ran_out_before_confirming_places_nothing(): void
    {
        $this->cartWith(5, 100, 10);
        $this->db->stock['7_1']->stock_qty = 2;     // someone else bought it meanwhile

        $this->submit()->assertRedirect('/checkout');
        $this->assertSame([], $this->db->orders);
        $this->assertSame(0, $this->db->stock['7_1']->reserved_qty);
        $this->assertNotNull($this->cartStore->forUser(5));                    // still in the cart, to be corrected
        $this->get('/checkout')->assertSee('toast(', false);
    }

    public function test_a_failure_while_telling_the_shop_does_not_undo_or_hide_the_order(): void
    {
        $this->cartWith();
        $this->app->instance(\App\Services\Orders\OrderNotifier::class, new class implements \App\Services\Orders\OrderNotifier
        {
            public function placed(iterable $orders): void
            {
                throw new \RuntimeException('queue is down');
            }

            public function paymentConfirmed(\App\Models\Order $order): void {}

            public function cancelled(\App\Models\Order $order): void {}
        });

        $this->submit()->assertRedirect('/order/placed?order=1');      // the order is placed, so the customer is told so
        $this->assertCount(1, $this->db->orders);
        $this->submit()->assertRedirect('/order/placed?order=1');      // and a repeat still just shows it
        $this->assertCount(1, $this->db->orders);
    }

    public function test_an_unexpected_failure_is_reported_nicely_and_the_page_can_try_again(): void
    {
        $this->cartWith();
        $this->mock(\App\Services\Orders\OrderPlacementService::class, fn ($m) => $m->shouldReceive('place')->once()->andThrow(new \RuntimeException('database went away')));

        $this->submit()->assertRedirect('/checkout');
        $this->get('/checkout')->assertSee('We could not place your order. Please try again.');
        // a failure does not use up the token, so the same page can submit again
        $this->assertNull(\Illuminate\Support\Facades\Cache::get('checkout.placed.5.'.self::TOKEN));
    }

    public function test_an_online_order_waits_unpaid_on_the_pay_page_and_nothing_is_charged_yet(): void
    {
        $this->cartWith(2, 100);

        $this->submit(['payment_method' => 'online'])->assertRedirect('/order/pay?order_id=1&start=1');

        $this->assertSame('pending', $this->db->payments[1]->status);
        $this->assertSame([], $this->razorpay->created);                        // no Razorpay order, no charge until the customer pays
        $this->assertNull($this->cartStore->forUser(5));
    }

    // ------------------------------------------------------------------ the pay page

    private function onlineOrder(): int
    {
        $this->cartWith(2, 100);
        $this->submit(['payment_method' => 'online'])->assertRedirect();

        return 1;
    }

    private function create(int $orderId)
    {
        return $this->me()->postJson('/payments/create', ['order_id' => $orderId]);
    }

    private function verify(string $razorpayOrder = 'order_rzp_1', string $payment = 'pay_1')
    {
        return $this->me()->postJson('/payments/verify', ['razorpay_order_id' => $razorpayOrder, 'razorpay_payment_id' => $payment, 'razorpay_signature' => 'sig']);
    }

    private function captured(): void
    {
        $this->razorpay->remote = ['order_id' => 'order_rzp_1', 'amount' => 20000, 'currency' => 'INR', 'status' => 'captured', 'method' => 'upi'];
    }

    public function test_the_pay_page_shows_the_amount_and_opens_the_window_only_when_asked(): void
    {
        $id = $this->onlineOrder();

        $this->me()->get("/order/pay?order_id=$id")->assertOk()
            ->assertSee('Pay for your order')->assertSee('₹200.00')->assertSee('id="pay-now"', false)->assertSee('data-start="0"', false)
            ->assertSee('data-order="1"', false)->assertSee('assets/js/modules/pay.js', false)->assertSee('assets/js/pay.js', false);
        $this->me()->get("/order/pay?order_id=$id&start=1")->assertSee('data-start="1"', false);
    }

    public function test_nothing_is_offered_for_an_order_that_is_paid_cod_or_not_yours(): void
    {
        $this->me()->get('/order/pay')->assertOk()->assertSee('No order selected');
        $this->me()->get('/order/pay?order_id=77')->assertOk()->assertSee('Order not found');

        $id = $this->onlineOrder();
        $this->create($id)->assertOk();
        $this->captured();
        $this->verify()->assertOk();
        $this->me()->get("/order/pay?order_id=$id&start=1")->assertSee('This order is already paid')->assertDontSee('id="pay-now"', false);

        $this->cartWith(1, 50);
        $this->submit(['payment_method' => 'cod', 'checkout_token' => 'bbbbbbbb-4f89-41d3-9a0c-0305e82c3301'])->assertRedirect();
        $cod = array_key_last($this->db->orders);
        $this->me()->get("/order/pay?order_id=$cod")->assertSee('Nothing to pay for this order')->assertSee('paid on delivery');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer(6))->get("/order/pay?order_id=$id")->assertSee('Order not found');
        $this->app['auth']->forgetGuards();
        $this->get("/order/pay?order_id=$id")->assertRedirect('/login?next=%2Forder%2Fpay%3Forder_id%3D1');
    }

    public function test_a_failed_attempt_is_explained_and_can_be_retried(): void
    {
        $id = $this->onlineOrder();
        $this->create($id)->assertOk();
        $this->razorpay->signatureOk = false;
        $this->verify()->assertStatus(400);

        $this->me()->get("/order/pay?order_id=$id")->assertSee('Your last attempt did not go through. Nothing was charged for it.')->assertSee('id="pay-now"', false);
    }

    // ------------------------------------------------------------------ paying: twice, and a closed window

    public function test_a_closed_window_leaves_the_order_unpaid_and_payable(): void
    {
        $id = $this->onlineOrder();
        $this->create($id)->assertOk();            // the window opened ... and was closed: /payments/verify is never called

        $this->assertSame('initiated', collect($this->db->payments)->last()->status);
        $this->me()->get("/order/placed?order=$id")->assertSee('Order placed - payment pending')->assertSee('Pay now');
        $this->me()->get("/order/pay?order_id=$id")->assertSee('id="pay-now"', false);

        $this->create($id)->assertOk()->assertJsonPath('message', 'Razorpay order already exists');   // reopening reuses the same Razorpay order
        $this->assertCount(1, $this->razorpay->created);
    }

    public function test_paying_twice_charges_and_settles_once(): void
    {
        $id = $this->onlineOrder();
        $this->create($id)->assertOk();
        $this->captured();

        $this->verify()->assertOk();
        $this->verify()->assertOk();               // a second tap, or the page reloading after paying

        $this->assertSame(8, $this->db->stock['7_1']->stock_qty);               // sold once: 10 - 2
        $this->assertSame(0, $this->db->stock['7_1']->reserved_qty);
        $this->assertSame(['paid'], collect($this->db->payments)->pluck('status')->unique()->filter(fn ($s) => $s === 'paid')->values()->all());
        $this->assertCount(1, $this->razorpay->created);

        // once paid, asking for a new Razorpay order is refused - the customer cannot be charged again
        $this->create($id)->assertStatus(400);
        $this->assertCount(1, $this->razorpay->created);
    }

    public function test_a_payment_razorpay_does_not_confirm_never_marks_the_order_paid(): void
    {
        $id = $this->onlineOrder();
        $this->create($id)->assertOk();

        $this->razorpay->remote = null;                                         // Razorpay cannot be reached
        $this->verify()->assertStatus(503);                                     // "try again shortly": the order stays unpaid
        $this->me()->get("/order/placed?order=$id")->assertSee('payment pending')->assertDontSee('Payment received');

        $this->razorpay->remote = ['order_id' => 'order_rzp_1', 'amount' => 100, 'currency' => 'INR', 'status' => 'captured'];   // wrong amount
        $this->verify()->assertStatus(400);
        $this->me()->get("/order/pay?order_id=$id")->assertSee('id="pay-now"', false);
    }

    public function test_after_paying_the_confirmation_says_payment_received(): void
    {
        $id = $this->onlineOrder();
        $this->create($id)->assertOk();
        $this->captured();
        $this->verify()->assertOk();

        $this->me()->get("/order/placed?order=$id")->assertOk()->assertSee('Payment received. We\'re getting your order ready.', false)->assertDontSee('Pay now');
    }

    // ------------------------------------------------------------------ the confirmation page

    public function test_without_an_order_the_confirmation_goes_to_my_orders(): void
    {
        $this->me()->get('/order/placed')->assertRedirect('/profile?tab=orders');
    }

    public function test_someone_elses_order_is_not_shown(): void
    {
        $this->cartWith();
        $this->submit()->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer(6))->get('/order/placed?order=1')->assertRedirect('/profile?tab=orders');
        $this->app['auth']->forgetGuards();
        $this->get('/order/placed?order=1')->assertRedirect('/login?next=%2Forder%2Fplaced%3Forder%3D1');
    }

    // ------------------------------------------------------------------ the result page tells the truth

    public function test_the_result_page_says_successful_only_for_a_paid_order(): void
    {
        $id = $this->onlineOrder();

        // a hand-typed success for an unpaid order is not believed
        $html = $this->me()->get("/payment/result?status=success&order=$id")->assertOk()->assertSee('Payment Failed')->assertDontSee('Payment Successful')->assertSee('Retry Payment')->getContent();
        $this->assertStringContainsString('/order/pay?order_id=1', $html);

        $this->create($id)->assertOk();
        $this->captured();
        $this->verify()->assertOk();

        $this->me()->get("/payment/result?status=failed&order=$id&reason=nope")->assertSee('Payment Successful')->assertSee('#1')->assertDontSee('Payment Failed');   // our records win
        $this->me()->get("/payment/result?order=$id")->assertSee('Payment Successful');
    }

    public function test_a_failure_reason_is_shown_safely(): void
    {
        $id = $this->onlineOrder();

        $this->me()->get("/payment/result?status=failed&order=$id&reason=".urlencode('<script>alert(1)</script>Card declined'))->assertOk()
            ->assertSee('Card declined')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->me()->get('/payment/result?status=failed')->assertSee("We couldn't process your payment")->assertSee('Retry Payment');
    }

    public function test_a_guest_or_a_stranger_gets_no_claim_either_way(): void
    {
        $id = $this->onlineOrder();

        $this->app['auth']->forgetGuards();
        $this->get("/payment/result?status=success&order=$id")->assertOk()->assertSee('We could not check this payment')->assertDontSee('Payment Successful');

        $this->actingAs($this->customer(6))->get("/payment/result?status=success&order=$id")->assertOk()->assertSee('We could not check this payment')->assertDontSee('Payment Successful');
        $this->actingAs($this->customer(6))->get("/payment/result?status=failed&order=$id")->assertSee('Payment Failed');
    }

    // ------------------------------------------------------------------ demo mode untouched
}
