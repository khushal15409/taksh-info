<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Tests\OrderTestCase;

class OrdersTest extends OrderTestCase
{
    private function user(int $id = 5)
    {
        return $this->customer($id);
    }

    /** An order of the customer straight in the store (no cart needed), with one line of 2 x Rs 100 and its stock reserved. */
    private function order(array $attrs = [], string $method = 'cod', string $payStatus = 'pending', ?string $razorpayPaymentId = null): Order
    {
        $variant = $this->sold(1, 100, 10);
        $this->db->stock['7_1']->reserved_qty = 2;
        $o = $this->checkoutStore->createOrder($attrs + [
            'user_id' => 5, 'vendor_id' => 7, 'address_id' => 1, 'order_number' => 'ORD'.str_pad((string) ($this->db->nextOrder), 8, 'X'), 'delivery_type' => 'normal',
            'order_status' => 'pending', 'order_type' => 'fm', 'items_total' => 150, 'taksh_charge_total' => 50, 'platform_fee' => 5, 'quick_delivery_fee' => 0, 'discount_total' => 0, 'grand_total' => 205,
        ]);
        $this->checkoutStore->createItem($o, ['product_variant_id' => $variant->id, 'qty' => 2, 'price' => 100, 'product_name' => 'Item 1', 'image_url' => null, 'return_days' => 7, 'commission_amount' => 9]);
        $this->checkoutStore->createCharge($o, ['type' => 'platform', 'label' => 'Platform Fees', 'amount' => 5, 'is_discount' => false]);
        $this->checkoutStore->createPayment(['order_id' => $o->id, 'gateway' => $method === 'online' ? 'razorpay' : 'cod', 'amount' => 20500, 'currency' => 'INR', 'status' => $payStatus, 'method' => $method, 'razorpay_payment_id' => $razorpayPaymentId, 'raw_response' => ['secret' => 'x']]);

        return $o;
    }

    // ------------------------------------------------------------------ list (H1) + detail (H2)

    public function test_the_list_is_the_customers_own_newest_first_and_filterable(): void
    {
        $a = $this->order(['order_status' => 'delivered']);
        $b = $this->order(['order_status' => 'pending']);
        $this->order(['user_id' => 9]);                                                        // someone else's
        $this->order(['delivery_type' => '30_min', 'is_express' => true, 'order_status' => 'pending']);

        $res = $this->actingAs($this->user())->getJson('/order/orders')->assertOk()->assertJsonPath('message', 'Orders fetched successfully')->assertJsonPath('data.total', 3);
        $this->assertSame([4, 2, 1], collect($res->json('data.data'))->pluck('id')->all());

        $this->actingAs($this->user())->getJson('/order/orders?order_status=delivered')->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $a->id);
        $this->actingAs($this->user())->getJson('/order/orders?delivery_mode=quick')->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.is_quick', true);
        $this->actingAs($this->user())->getJson('/order/orders?delivery_mode=standard')->assertJsonPath('data.total', 2);
        $this->actingAs($this->user())->getJson('/order/orders?delivery_mode=bogus')->assertStatus(422);
        $this->app['auth']->forgetGuards();
        $this->getJson('/order/orders')->assertStatus(401);
    }

    public function test_an_order_shows_its_bill_and_never_the_vendor_money(): void
    {
        $o = $this->order([], 'online', 'initiated', 'pay_1');

        $res = $this->actingAs($this->user())->getJson('/order/orders/'.$o->id)->assertOk()
            ->assertJsonPath('message', 'Order details fetched successfully')
            ->assertJsonPath('data.order_status', 'pending')->assertJsonPath('data.grand_total', 205)->assertJsonPath('data.platform_fee', 5)
            ->assertJsonPath('data.address.pincode', '395001')->assertJsonPath('data.vendor.shop_name', 'Fresh Mart')
            ->assertJsonPath('data.items.0.product_name', 'Item 1')->assertJsonPath('data.items.0.qty', 2)
            ->assertJsonPath('data.charges.0.label', 'Platform Fees')->assertJsonPath('data.payment_status', 'pending');

        $json = json_encode($res->json('data'));
        $this->assertStringNotContainsString('commission', $json);
        $this->assertStringNotContainsString('raw_response', $json);
        $this->assertStringNotContainsString('secret', $json);

        $this->actingAs($this->user(9))->getJson('/order/orders/'.$o->id)->assertStatus(404)->assertJsonPath('message', 'Order not found');
    }

    // ------------------------------------------------------------------ cancel (H3)

    public function test_a_pending_cod_order_is_cancelled_and_its_stock_goes_back(): void
    {
        $o = $this->order();
        $this->db->couponUsages[] = [1, 5, $o->id, 50.0];

        $this->actingAs($this->user())->postJson('/order/orders/'.$o->id.'/cancel')->assertOk()
            ->assertJsonPath('message', 'Order cancelled successfully')
            ->assertJsonPath('data.order_status', 'cancelled')->assertJsonPath('data.refund_status', 'not_applicable');

        $this->assertSame('cancelled', $o->order_status);
        $this->assertSame(0, $this->db->stock['7_1']->reserved_qty);          // the reservation is released
        $this->assertSame(10, $this->db->stock['7_1']->stock_qty);
        $this->assertSame([$o->id], $this->orderStore->couponReleased);
        $this->assertSame([$o->id], $this->shipments->cancelled);
        $this->assertSame([$o->id], $this->notifier->cancelled);
    }

    public function test_a_paid_order_gets_its_sold_stock_back_and_is_flagged_for_a_manual_refund(): void
    {
        $o = $this->order(['order_status' => 'confirmed', 'stock_committed_at' => now()], 'online', 'paid', 'pay_9');
        $this->db->stock['7_1']->reserved_qty = 0;
        $this->db->stock['7_1']->stock_qty = 8;                                // 2 were sold when it was paid

        $this->actingAs($this->user())->postJson('/order/orders/'.$o->id.'/cancel')->assertOk()->assertJsonPath('data.refund_status', 'manual_required');

        $this->assertSame(10, $this->db->stock['7_1']->stock_qty);             // the sold stock goes back
        $payment = $this->db->payments[1];
        $this->assertSame('paid', $payment->status);                             // nothing was refunded, so it stays paid
        $this->assertTrue($payment->raw_response['refund_required']);
        $this->assertSame([], $this->razorpay->refunds);
    }

    public function test_with_automatic_refunds_on_the_payment_is_refunded_through_razorpay_once(): void
    {
        config(['services.razorpay.refund_on_cancel' => true]);
        $o = $this->order(['order_status' => 'confirmed', 'stock_committed_at' => now()], 'online', 'paid', 'pay_9');

        $this->actingAs($this->user())->postJson('/order/orders/'.$o->id.'/cancel')->assertOk()->assertJsonPath('data.refund_status', 'processed');

        $this->assertSame([['pay_9', 20500, ['reference' => 'order-refund-'.$o->id, 'type' => 'order', 'order_id' => (string) $o->id]]], $this->razorpay->refunds);
        $this->assertSame('refunded', $this->db->payments[1]->status);
    }

    public function test_an_existing_refund_is_adopted_and_a_gateway_failure_never_undoes_the_cancel(): void
    {
        config(['services.razorpay.refund_on_cancel' => true]);
        $o = $this->order(['order_status' => 'confirmed', 'stock_committed_at' => now()], 'online', 'paid', 'pay_9');
        $this->razorpay->existingRefunds = [['id' => 'rfnd_old', 'status' => 'pending', 'notes' => ['reference' => 'order-refund-'.$o->id]]];

        $this->actingAs($this->user())->postJson('/order/orders/'.$o->id.'/cancel')->assertOk()->assertJsonPath('data.refund_status', 'pending');
        $this->assertSame([], $this->razorpay->refunds);                         // adopted, not created again
        $this->assertSame('paid', $this->db->payments[1]->status);              // pending: not claimed as refunded

        $o2 = $this->order(['order_status' => 'confirmed', 'stock_committed_at' => now()], 'online', 'paid', 'pay_10');
        $this->razorpay->existingRefunds = [];
        $this->razorpay->failRefund = true;
        $this->actingAs($this->user())->postJson('/order/orders/'.$o2->id.'/cancel')->assertOk()->assertJsonPath('data.refund_status', 'failed')->assertJsonPath('data.order_status', 'cancelled');
        $this->assertSame('cancelled', $o2->order_status);
    }

    public function test_only_a_pending_or_confirmed_order_of_the_customer_can_be_cancelled(): void
    {
        $shipped = $this->order(['order_status' => 'shipped']);
        $this->actingAs($this->user())->postJson('/order/orders/'.$shipped->id.'/cancel')->assertStatus(400)->assertJsonPath('message', 'Order cannot be cancelled at this stage');

        $done = $this->order(['order_status' => 'cancelled']);
        $this->actingAs($this->user())->postJson('/order/orders/'.$done->id.'/cancel')->assertStatus(400)->assertJsonPath('message', 'Order is already cancelled');

        $other = $this->order(['user_id' => 9]);
        $this->actingAs($this->user())->postJson('/order/orders/'.$other->id.'/cancel')->assertStatus(404);
        $this->assertSame('pending', $other->order_status);
        $this->assertSame([], $this->shipments->cancelled);
    }

    // ------------------------------------------------------------------ tracking (H7)

    public function test_delivery_tracking_for_the_customer_who_ordered(): void
    {
        $o = $this->order(['delivery_type' => '30_min', 'is_express' => true, 'order_status' => 'out_for_delivery']);
        $o->setRelation('address', (new \App\Models\Address)->forceFill(['latitude' => 23.0300, 'longitude' => 72.5800]))
            ->setRelation('deliveryMan', (new \App\Models\DeliveryMan)->forceFill(['id' => 3, 'user_id' => 77, 'name' => 'Ravi', 'mobile_number' => '9000000000']))
            ->setRelation('deliveryLocation', null);
        $this->orderStore->tracking[$o->id] = $o;

        $this->actingAs($this->user())->getJson('/order/orders/'.$o->id.'/delivery-location')->assertOk()
            ->assertJsonPath('message', 'Delivery location not yet available')->assertJsonPath('data.location', null);

        $o->setRelation('deliveryLocation', (new \App\Models\DeliveryLocation)->forceFill(['latitude' => 23.0301, 'longitude' => 72.5801, 'updated_at' => now()]));

        $res = $this->actingAs($this->user())->getJson('/order/orders/'.$o->id.'/delivery-location')->assertOk()
            ->assertJsonPath('message', 'Location retrieved successfully')->assertJsonPath('data.delivery_boy.name', 'Ravi')->assertJsonPath('data.reached_nearby', true);
        $this->assertEqualsWithDelta(23.0301, $res->json('data.location.latitude'), 0.00001);
        $this->assertLessThan(20, $res->json('data.distance_to_destination'));             // metres

        $this->actingAs($this->user(9))->getJson('/order/orders/'.$o->id.'/delivery-location')->assertStatus(403);
        $this->actingAs($this->user(77))->getJson('/order/orders/'.$o->id.'/delivery-location')->assertOk();   // the assigned delivery person
        $this->actingAs($this->user())->getJson('/order/orders/999/delivery-location')->assertStatus(404);
    }

    // ------------------------------------------------------------------ invoice (H6)

    public function test_the_invoice_is_the_customers_own_and_comes_from_taksh_admin(): void
    {
        config(['taksh.admin_url' => 'https://admin.test']);
        \Illuminate\Support\Facades\Http::fake(['admin.test/*' => \Illuminate\Support\Facades\Http::response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $o = $this->order(['order_number' => 'ORDABC12345']);

        $res = $this->actingAs($this->user())->get('/orders/ORDABC12345/invoice?part=tax')->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('invoice-ORDABC12345.pdf', $res->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.4 fake', $res->getContent());
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://admin.test/api/orders/ORDABC12345/invoice') && $r['part'] === 'tax');

        $this->actingAs($this->user(9))->get('/orders/ORDABC12345/invoice')->assertStatus(404);          // someone else's order looks like no order
        $this->actingAs($this->user())->get('/orders/NOPE/invoice')->assertStatus(404);

        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory);
        \Illuminate\Support\Facades\Http::fake(['admin.test/*' => \Illuminate\Support\Facades\Http::response('oops', 500)]);
        $this->actingAs($this->user())->get('/orders/ORDABC12345/invoice')->assertStatus(503);
    }
}
