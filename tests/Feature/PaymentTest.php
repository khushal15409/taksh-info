<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use Tests\OrderTestCase;
use Tests\Support\FakeVendor;

class PaymentTest extends OrderTestCase
{
    /** An online order of one shop (grand total 200 = 20000 paise), placed through the real endpoint. */
    private function onlineOrder(): int
    {
        $this->inCart($this->sold(1, 100, 10), 2);

        return $this->place(['payment_method' => 'online'])->assertOk()->json('data.id');
    }

    private function pay(string $path, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->customer(5))->postJson($path, $body);
    }

    private function create(int $orderId): \Illuminate\Testing\TestResponse
    {
        return $this->pay('/payments/create', ['order_id' => $orderId]);
    }

    private function verify(string $razorpayOrderId, string $paymentId = 'pay_1', string $sig = 'sig'): \Illuminate\Testing\TestResponse
    {
        return $this->pay('/payments/verify', ['razorpay_order_id' => $razorpayOrderId, 'razorpay_payment_id' => $paymentId, 'razorpay_signature' => $sig]);
    }

    // ------------------------------------------------------------------ create (G6, G8)

    public function test_creating_a_razorpay_order_for_the_amount_fixed_at_placement(): void
    {
        $orderId = $this->onlineOrder();

        $this->create($orderId)->assertOk()
            ->assertJsonPath('message', 'Razorpay order created successfully')
            ->assertJsonPath('data.razorpay_order_id', 'order_rzp_1')
            ->assertJsonPath('data.amount', 20000)
            ->assertJsonPath('data.currency', 'INR')
            ->assertJsonPath('data.razorpay_key', 'rzp_test_key');

        $this->assertSame([[20000, ['order_id' => $orderId, 'order_number' => $this->db->orders[$orderId]->order_number]]], $this->razorpay->created);
        $initiated = collect($this->db->payments)->firstWhere('status', 'initiated');
        $this->assertSame(['razorpay', 'order_rzp_1', 20000, 'online'], [$initiated->gateway, $initiated->razorpay_order_id, $initiated->amount, $initiated->method]);
    }

    public function test_a_retry_returns_the_open_razorpay_order_instead_of_creating_another(): void
    {
        $orderId = $this->onlineOrder();
        $this->create($orderId)->assertOk();

        $this->create($orderId)->assertOk()->assertJsonPath('message', 'Razorpay order already exists')->assertJsonPath('data.razorpay_order_id', 'order_rzp_1');
        $this->assertCount(1, $this->razorpay->created);
    }

    public function test_only_an_unpaid_online_order_of_the_customer_can_be_paid(): void
    {
        $this->create(99)->assertStatus(404)->assertJsonPath('message', 'Order not found');

        $this->inCart($this->sold(1, 100, 10), 1);
        $cod = $this->place()->json('data.id');
        $this->create($cod)->assertStatus(400)->assertJsonPath('message', 'Payment method is not online');

        $orderId = $this->onlineOrder();
        $this->actingAs($this->customer(9))->postJson('/payments/create', ['order_id' => $orderId])->assertStatus(404);

        $this->db->orders[$orderId]->order_status = 'cancelled';
        $this->create($orderId)->assertStatus(400)->assertJsonPath('message', 'Order is not in a valid state for payment');

        $this->db->orders[$orderId]->order_status = 'pending';
        $this->db->payments[2]->status = 'paid';
        $this->create($orderId)->assertStatus(400)->assertJsonPath('message', 'Order is already paid');
    }

    public function test_an_order_total_that_no_longer_matches_is_never_charged_silently(): void
    {
        $orderId = $this->onlineOrder();
        $this->db->orders[$orderId]->grand_total = 250;

        $this->create($orderId)->assertStatus(409)->assertJsonPath('message', 'Order amount mismatch');
        $this->assertSame([], $this->razorpay->created);
    }

    public function test_a_gateway_failure_creates_no_payment_row(): void
    {
        $orderId = $this->onlineOrder();
        $this->razorpay->failCreate = true;

        $this->create($orderId)->assertStatus(500)->assertJsonPath('message', 'Failed to create payment order');
        $this->assertNull(collect($this->db->payments)->firstWhere('status', 'initiated'));
    }

    public function test_orders_of_one_checkout_are_paid_with_one_razorpay_order_for_their_sum(): void
    {
        $this->checkoutStore->vendors[8] = (new FakeVendor)->forceFill(['id' => 8, 'status' => 'approved', 'shop_name' => 'Dairy Hub']);
        $this->cartCatalog->approved = [7, 8];
        $this->inCart($this->sold(1, 100), 2);                                   // 200
        $this->inCart($this->sold(2, 100, 10, ['vendor_id' => 8]), 1);           // 100
        $this->place(['payment_method' => 'online'])->assertOk();

        $this->create(1)->assertOk()->assertJsonPath('data.amount', 30000)->assertJsonPath('data.razorpay_order_id', 'order_rzp_1');

        $initiated = collect($this->db->payments)->where('status', 'initiated');
        $this->assertCount(2, $initiated);                                       // each order keeps its own payment row and amount
        $this->assertEqualsCanonicalizing([20000, 10000], $initiated->pluck('amount')->all());
        $this->create(2)->assertOk()->assertJsonPath('data.razorpay_order_id', 'order_rzp_1');   // the second order reuses it
        $this->assertCount(1, $this->razorpay->created);
    }

    // ------------------------------------------------------------------ verify (G6)

    public function test_a_verified_payment_confirms_the_order_and_turns_the_reservation_into_sold_stock(): void
    {
        $orderId = $this->onlineOrder();
        $this->create($orderId);
        $this->razorpay->remote = ['order_id' => 'order_rzp_1', 'amount' => 20000, 'currency' => 'INR', 'status' => 'captured', 'method' => 'upi'];

        $this->verify('order_rzp_1', 'pay_77')->assertOk()
            ->assertJsonPath('message', 'Payment verified successfully')
            ->assertJsonPath('data.status', 'paid')->assertJsonPath('data.order_id', $orderId)->assertJsonPath('data.order_status', 'confirmed');

        $order = $this->db->orders[$orderId];
        $this->assertSame('confirmed', $order->order_status);
        $this->assertNotNull($order->confirmed_at);
        $this->assertNotNull($order->stock_committed_at);
        $this->assertSame([0, 8], [$this->db->stock['7_1']->reserved_qty, $this->db->stock['7_1']->stock_qty]);   // 2 sold: 10 -> 8, nothing reserved

        $paid = collect($this->db->payments)->firstWhere('status', 'paid');
        $this->assertSame(['pay_77', 'upi'], [$paid->razorpay_payment_id, $paid->method]);
        $this->assertTrue($paid->raw_response['razorpay_payment']['status'] === 'captured');
        $this->assertSame([$orderId], $this->notifier->confirmed);
    }

    public function test_verifying_twice_deducts_stock_once(): void
    {
        $orderId = $this->onlineOrder();
        $this->create($orderId);
        $this->razorpay->remote = ['order_id' => 'order_rzp_1', 'amount' => 20000, 'currency' => 'INR', 'status' => 'captured'];

        $this->verify('order_rzp_1')->assertOk();
        $this->verify('order_rzp_1')->assertOk()->assertJsonPath('message', 'Payment already verified');

        $this->assertSame(8, $this->db->stock['7_1']->stock_qty);
        $this->assertCount(1, $this->notifier->confirmed);
    }

    public function test_a_bad_signature_proves_nothing_and_fails_only_an_open_payment(): void
    {
        $orderId = $this->onlineOrder();
        $this->create($orderId);
        $this->razorpay->signatureOk = false;

        $this->verify('order_rzp_1')->assertStatus(400)->assertJsonPath('message', 'Payment verification failed');

        $failed = collect($this->db->payments)->firstWhere('razorpay_order_id', 'order_rzp_1');
        $this->assertSame('failed', $failed->status);
        $this->assertTrue($failed->raw_response['verification_failed']);
        $this->assertSame('pending', $this->db->orders[$orderId]->order_status);          // never confirmed

        // a payment that is already paid is not touched by a forged call
        $failed->status = 'paid';
        $this->verify('order_rzp_1')->assertStatus(400);
        $this->assertSame('paid', $failed->status);
    }

    public function test_the_gateway_must_agree_with_our_record(): void
    {
        $orderId = $this->onlineOrder();
        $this->create($orderId);
        $good = ['order_id' => 'order_rzp_1', 'amount' => 20000, 'currency' => 'INR', 'status' => 'captured'];

        foreach ([['amount' => 100], ['order_id' => 'order_other'], ['currency' => 'USD'], ['status' => 'failed']] as $bad) {
            $this->razorpay->remote = $bad + $good;
            $this->verify('order_rzp_1')->assertStatus(400)->assertJsonPath('message', 'Payment verification failed');
        }

        $this->razorpay->remote = null;                                                   // Razorpay could not be asked
        $this->verify('order_rzp_1')->assertStatus(503);

        $this->assertSame('pending', $this->db->orders[$orderId]->order_status);
        $this->assertSame(0, $this->db->stock['7_1']->reserved_qty - 2);                  // the reservation is untouched
    }

    public function test_someone_elses_payment_cannot_be_verified(): void
    {
        $this->create($this->onlineOrder());
        $this->razorpay->remote = ['order_id' => 'order_rzp_1', 'amount' => 20000, 'currency' => 'INR', 'status' => 'captured'];

        $this->actingAs($this->customer(9))->postJson('/payments/verify', ['razorpay_order_id' => 'order_rzp_1', 'razorpay_payment_id' => 'p', 'razorpay_signature' => 's'])
            ->assertStatus(403)->assertJsonPath('message', 'Unauthorized');
        $this->verify('order_unknown')->assertStatus(404)->assertJsonPath('message', 'Payment not found');
    }

    public function test_a_payment_for_a_cancelled_order_never_revives_it(): void
    {
        $orderId = $this->onlineOrder();
        $this->create($orderId);
        $this->db->orders[$orderId]->order_status = 'cancelled';
        $this->razorpay->remote = ['order_id' => 'order_rzp_1', 'amount' => 20000, 'currency' => 'INR', 'status' => 'captured'];

        $this->verify('order_rzp_1')->assertStatus(409)->assertJsonPath('message', 'This order was cancelled, so it was not confirmed. The payment will be refunded.');

        $this->assertSame('cancelled', $this->db->orders[$orderId]->order_status);
        $paid = collect($this->db->payments)->firstWhere('razorpay_order_id', 'order_rzp_1');
        $this->assertTrue($paid->raw_response['needs_refund']);                           // the money is recorded and flagged for a refund
        $this->assertNull($this->db->orders[$orderId]->stock_committed_at);
    }

    public function test_a_group_is_settled_together_and_the_sum_is_checked(): void
    {
        $this->checkoutStore->vendors[8] = (new FakeVendor)->forceFill(['id' => 8, 'status' => 'approved']);
        $this->cartCatalog->approved = [7, 8];
        $this->inCart($this->sold(1, 100), 2);
        $this->inCart($this->sold(2, 100, 10, ['vendor_id' => 8]), 1);
        $this->place(['payment_method' => 'online']);
        $this->create(1);

        $this->razorpay->remote = ['order_id' => 'order_rzp_1', 'amount' => 20000, 'currency' => 'INR', 'status' => 'captured'];   // one order's amount, not the sum
        $this->verify('order_rzp_1')->assertStatus(400);

        $this->razorpay->remote = ['order_id' => 'order_rzp_1', 'amount' => 30000, 'currency' => 'INR', 'status' => 'captured'];
        $this->verify('order_rzp_1')->assertOk();

        $this->assertSame(['confirmed', 'confirmed'], [$this->db->orders[1]->order_status, $this->db->orders[2]->order_status]);
        $this->assertSame([1, 2], $this->notifier->confirmed);
    }

    // ------------------------------------------------------------------ status (G10)

    public function test_payment_status(): void
    {
        $orderId = $this->onlineOrder();

        $this->pay('/payments/create', ['order_id' => $orderId]);
        $this->actingAs($this->customer(5))->getJson('/payments/status/'.$orderId)->assertOk()
            ->assertJsonPath('message', 'Payment status retrieved')
            ->assertJsonPath('data.payment_status', 'pending')->assertJsonPath('data.order_status', 'pending')
            ->assertJsonPath('data.payment.razorpay_order_id', 'order_rzp_1')->assertJsonPath('data.payment.amount', 20000);

        $this->db->payments[collect($this->db->payments)->keys()->last()]->status = 'success';
        $this->actingAs($this->customer(5))->getJson('/payments/status/'.$orderId)->assertJsonPath('data.payment_status', 'paid');

        $this->actingAs($this->customer(9))->getJson('/payments/status/'.$orderId)->assertStatus(404);
        $this->app['auth']->forgetGuards();
        $this->getJson('/payments/status/'.$orderId)->assertStatus(401);
    }
}
