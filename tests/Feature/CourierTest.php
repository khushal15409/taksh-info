<?php

namespace Tests\Feature;

use App\Models\CourierDeliveryPartner;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use App\Repositories\Contracts\CourierShipmentRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Courier\CourierPhotoStore;
use App\Services\Shiprocket\ShiprocketApi;
use App\Services\Shiprocket\ShiprocketException;
use Carbon\Carbon;
use Tests\OrderTestCase;
use Tests\Support\FakeCourierBookingRepository;
use Tests\Support\FakeCourierDb;
use Tests\Support\FakeCourierPhotoStore;
use Tests\Support\FakeCourierShipmentRepository;
use Tests\Support\FakeShiprocketApi;

require_once __DIR__.'/../Support/CourierFakes.php';

class CourierTest extends OrderTestCase
{
    private FakeCourierDb $cdb;

    private FakeShiprocketApi $sr;

    private FakeCourierPhotoStore $photos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cdb = new FakeCourierDb;
        $this->app->instance(CourierBookingRepositoryInterface::class, new FakeCourierBookingRepository($this->cdb));
        $this->app->instance(CourierShipmentRepositoryInterface::class, new FakeCourierShipmentRepository($this->cdb));
        $this->app->instance(ShiprocketApi::class, $this->sr = new FakeShiprocketApi);
        $this->app->instance(CourierPhotoStore::class, $this->photos = new FakeCourierPhotoStore);
        config(['shiprocket.pickup_location' => 'Primary', 'shiprocket.default_declared_value' => 500, 'shiprocket.refund_policy' => 'full']);
    }

    private function parcel(array $extra = []): array
    {
        return $extra + ['weight_grams' => 500, 'dimension_unit' => 'cm', 'length' => 10, 'width' => 10, 'height' => 10, 'pickup_pincode' => '380001', 'delivery_pincode' => '400001'];
    }

    private function bookingBody(array $extra = []): array
    {
        return $this->parcel($extra + [
            'customer_name' => 'Asha', 'customer_mobile' => '9876543210', 'pickup_address' => '1 Pickup Rd', 'delivery_name' => 'Ravi',
            'delivery_mobile' => '9123456780', 'delivery_address' => '2 Drop St',
        ]);
    }

    private function me(int $id = 5)
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($this->customer($id));
    }

    private static function sr(string $category, ?int $status = null, ?string $msg = null): ShiprocketException
    {
        return new ShiprocketException('x', $category, $status, null, $msg);
    }

    // ------------------------------------------------------------------ K5 partners, K6 quote

    public function test_everything_needs_a_login(): void
    {
        foreach (['/service/courier/delivery-partners', '/service/courier/bookings', '/service/courier/bookings/1', '/service/courier/bookings/1/invoice'] as $path) {
            $this->getJson($path)->assertStatus(401);
        }
        foreach (['quote', 'bookings', 'payment/create', 'payment/verify', 'bookings/1/cancel', 'bookings/1/shiprocket', 'bookings/1/track'] as $path) {
            $this->postJson("/service/courier/$path")->assertStatus(401);
        }
    }

    public function test_the_active_delivery_partners_are_listed(): void
    {
        $this->cdb->partners = [
            (new CourierDeliveryPartner)->forceFill(['id' => 1, 'name' => 'Local Express', 'slug' => 'local-express', 'is_active' => true]),
            (new CourierDeliveryPartner)->forceFill(['id' => 2, 'name' => 'Old', 'slug' => 'old', 'is_active' => false]),
        ];

        $this->me()->getJson('/service/courier/delivery-partners')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Local Express');
    }

    public function test_a_quote_adds_the_service_charge_to_each_live_shiprocket_rate(): void
    {
        $this->me()->postJson('/service/courier/quote', $this->parcel())->assertOk()
            ->assertJsonPath('message', 'Delivery partners fetched successfully')->assertJsonPath('data.weight_kg', 0.5)
            ->assertJsonPath('data.couriers.0.courier_name', 'Delhivery')->assertJsonPath('data.couriers.0.rate', 80)
            ->assertJsonPath('data.couriers.0.service_charge', 20)->assertJsonPath('data.couriers.0.total_amount', 100)->assertJsonPath('data.couriers.1.total_amount', 140);
    }

    public function test_the_admins_courier_service_charge_wins_and_a_missing_one_blocks_quoting(): void
    {
        $this->cdb->serviceCharge = null;
        config(['shiprocket.platform_charge' => '15']);
        $this->me()->postJson('/service/courier/quote', $this->parcel())->assertOk()->assertJsonPath('data.couriers.0.total_amount', 95);

        config(['shiprocket.platform_charge' => null]);
        $this->me()->postJson('/service/courier/quote', $this->parcel())->assertStatus(503)->assertJsonPath('message', 'Courier booking is temporarily unavailable. Please try again later.');
    }

    public function test_quote_problems_are_explained_to_the_customer(): void
    {
        $this->me()->postJson('/service/courier/quote', $this->parcel(['delivery_pincode' => null]))->assertStatus(400);
        $this->me()->postJson('/service/courier/quote', $this->parcel(['pickup_pincode' => '12']))->assertStatus(400);

        $this->sr->quoteFails = self::sr(ShiprocketException::NO_SERVICE);
        $this->me()->postJson('/service/courier/quote', $this->parcel())->assertStatus(422)->assertJsonPath('success', false);

        $this->sr->quoteFails = self::sr(ShiprocketException::CLIENT_ERROR, 422);
        $this->me()->postJson('/service/courier/quote', $this->parcel())->assertStatus(422)->assertJsonPath('message', 'Please check the pickup and delivery pincodes');

        $this->sr->quoteFails = self::sr(ShiprocketException::SERVER_ERROR, 500);
        $this->me()->postJson('/service/courier/quote', $this->parcel())->assertStatus(503)->assertJsonPath('message', 'Courier rates are temporarily unavailable. Please try again later.');
    }

    public function test_without_pincodes_the_legacy_partners_are_priced_by_weight(): void
    {
        $this->cdb->partners = [(new CourierDeliveryPartner)->forceFill(['id' => 1, 'name' => 'Local', 'slug' => 'local', 'base_charge' => 30, 'charge_per_kg' => 20, 'is_active' => true])];

        $this->me()->postJson('/service/courier/quote', ['weight_grams' => 1500, 'dimension_unit' => 'cm', 'length' => 10, 'width' => 10, 'height' => 10])->assertOk()
            ->assertJsonPath('message', 'Quote computed successfully')->assertJsonPath('data.chargeable_weight_grams', 1500)->assertJsonPath('data.chargeable_weight_source', 'actual')
            ->assertJsonPath('data.quotes.0.total_amount', 60);   // 30 + 1.5 kg x 20
        $this->assertSame([], $this->sr->calls);
    }

    // ------------------------------------------------------------------ K7 book + pay

    public function test_a_booking_books_the_cheapest_courier_and_prices_it_on_the_server(): void
    {
        $this->me()->postJson('/service/courier/bookings', $this->bookingBody(['total_amount' => 1]))->assertStatus(201)
            ->assertJsonPath('message', 'Courier booking created successfully')
            ->assertJsonPath('data.total_amount', 100)->assertJsonPath('data.shiprocket_rate', 80)->assertJsonPath('data.service_charge', 20)
            ->assertJsonPath('data.courier_name', 'Delhivery')->assertJsonPath('data.status', 'pending')->assertJsonPath('data.payment_status', 'initiated')
            ->assertJsonPath('data.pricing.total_amount', 100)->assertJsonPath('data.payment.amount_in_paise', 10000)->assertJsonPath('data.payment.gateway_key', 'rzp_test_key');
    }

    public function test_the_payment_order_is_for_the_snapshot_total_in_paise(): void
    {
        $this->me()->postJson('/service/courier/bookings', $this->bookingBody())->assertStatus(201);

        $this->assertSame(10000, $this->razorpay->created[0][0]);
        $this->assertSame('CRB-201', $this->razorpay->created[0][1]['booking_number']);
        $b = $this->cdb->bookings[201];
        $this->assertSame(['12', 'Delhivery', 'initiated'], [$b->shiprocket_courier_company_id, $b->courier_name, $b->payment_status]);
    }

    public function test_a_named_courier_and_a_stale_quote(): void
    {
        $this->me()->postJson('/service/courier/bookings', $this->bookingBody(['shiprocket_courier_company_id' => '7', 'quoted_rate' => 120]))->assertStatus(201)->assertJsonPath('data.total_amount', 140);

        $this->me()->postJson('/service/courier/bookings', $this->bookingBody(['shiprocket_courier_company_id' => '12', 'quoted_rate' => 70]))
            ->assertStatus(409)->assertJsonPath('data.code', 'quote_changed')->assertJsonPath('message', 'The courier price has changed. Please refresh the quote.');
        $this->me()->postJson('/service/courier/bookings', $this->bookingBody(['shiprocket_courier_company_id' => '99']))->assertStatus(409)->assertJsonPath('data.code', 'quote_changed');
        $this->assertCount(1, $this->cdb->bookings);
    }

    public function test_booking_details_are_validated(): void
    {
        $this->me()->postJson('/service/courier/bookings', $this->bookingBody(['customer_mobile' => '12345']))->assertStatus(400);
        $this->me()->postJson('/service/courier/bookings', $this->bookingBody(['weight_grams' => 0]))->assertStatus(400);
        $this->me()->postJson('/service/courier/bookings', $this->bookingBody(['shiprocket_courier_company_id' => '12', 'courier_delivery_partner_id' => 1]))->assertStatus(400);
        $this->assertSame([], $this->cdb->bookings);
    }

    public function test_a_legacy_partner_booking_is_priced_locally_without_a_payment_order(): void
    {
        $this->cdb->partners = [(new CourierDeliveryPartner)->forceFill(['id' => 1, 'name' => 'Local', 'slug' => 'local', 'base_charge' => 30, 'charge_per_kg' => 20, 'is_active' => true])];

        $this->me()->postJson('/service/courier/bookings', $this->bookingBody(['courier_delivery_partner_id' => 1]))->assertStatus(201)
            ->assertJsonPath('data.total_amount', 40)->assertJsonPath('data.partner_name', 'Local')->assertJsonMissingPath('data.payment');
        $this->assertSame([], $this->razorpay->created);

        $this->me()->postJson('/service/courier/bookings', $this->bookingBody(['courier_delivery_partner_id' => 9]))->assertStatus(404)->assertJsonPath('message', 'Delivery partner not found or inactive');
    }

    public function test_the_payment_order_can_be_made_later_and_is_reused(): void
    {
        $this->razorpay->failCreate = true;
        $this->me()->postJson('/service/courier/bookings', $this->bookingBody())->assertStatus(201)->assertJsonPath('data.payment', null);

        $this->razorpay->failCreate = false;
        $this->me()->postJson('/service/courier/payment/create', ['courier_booking_id' => 201])->assertOk()
            ->assertJsonPath('message', 'Payment order created successfully')->assertJsonPath('data.amount', 10000)->assertJsonPath('data.amount_in_rupees', 100)->assertJsonPath('data.gateway_key', 'rzp_test_key');
        $this->me()->postJson('/service/courier/payment/create', ['courier_booking_id' => 201])->assertOk()->assertJsonPath('message', 'Payment order already exists');
        $this->assertCount(1, $this->razorpay->created);
    }

    public function test_a_changed_price_stops_the_payment_order(): void
    {
        $this->razorpay->failCreate = true;
        $this->me()->postJson('/service/courier/bookings', $this->bookingBody());
        $this->razorpay->failCreate = false;
        $this->sr->couriers[0]['rate'] = 90.0;

        $this->me()->postJson('/service/courier/payment/create', ['courier_booking_id' => 201])->assertStatus(409)->assertJsonPath('data.code', 'quote_changed');
        $this->me()->postJson('/service/courier/payment/create', ['courier_booking_id' => 999])->assertStatus(404)->assertJsonPath('message', 'Courier booking not found');
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer(6))->postJson('/service/courier/payment/create', ['courier_booking_id' => 201])->assertStatus(404);
    }

    private function verify(int $bookingId = 201, string $order = 'order_c501', int $user = 5)
    {
        return $this->me($user)->postJson('/service/courier/payment/verify', ['courier_booking_id' => $bookingId, 'razorpay_order_id' => $order, 'razorpay_payment_id' => 'pay_c1', 'razorpay_signature' => 'sig']);
    }

    private function pendingPayment(): array
    {
        $b = $this->cdb->shiprocketBooking(['payment_status' => 'initiated', 'payment_order_id' => 'order_open']);
        $p = $this->cdb->payment($b, ['gateway_order_id' => 'order_open']);
        $this->razorpay->remote = ['id' => 'pay_c1', 'order_id' => 'order_open', 'amount' => 10000, 'currency' => 'INR', 'status' => 'captured', 'method' => 'upi'];

        return [$b, $p];
    }

    public function test_a_verified_payment_marks_the_booking_paid_and_creates_the_shipment(): void
    {
        [$b] = $this->pendingPayment();

        $this->verify($b->id, 'order_open')->assertOk()->assertJsonPath('message', 'Payment verified successfully. Courier booking confirmed.')
            ->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.status', 'paid')->assertJsonPath('data.transaction_id', 'pay_c1')
            ->assertJsonPath('data.shipment.awb_code', 'AWB123')->assertJsonPath('data.shipment.shipment_status', 'pickup_requested')->assertJsonPath('data.shipment.action_required', null);

        $this->assertSame(['order', 'awb', 'pickup'], $this->sr->calls);
        $this->verify($b->id, 'order_open')->assertOk()->assertJsonPath('message', 'Payment already verified and booking confirmed');
        $this->assertSame(['order', 'awb', 'pickup'], $this->sr->calls);   // nothing is booked twice
    }

    public function test_a_bad_signature_or_mismatching_payment_marks_it_failed(): void
    {
        [$b, $p] = $this->pendingPayment();

        $this->razorpay->signatureOk = false;
        $this->verify($b->id, 'order_open')->assertStatus(400)->assertJsonPath('message', 'Payment verification failed.');
        $this->assertSame(['failed', 'failed'], [$p->status, $b->payment_status]);

        $p->status = 'initiated';
        $this->razorpay->signatureOk = true;
        $this->razorpay->remote['amount'] = 5;
        $this->verify($b->id, 'order_open')->assertStatus(400);
        $this->assertSame('failed', $p->status);
        $this->assertSame([], $this->sr->calls);
    }

    public function test_verification_is_for_the_customers_own_booking_and_order(): void
    {
        [$b] = $this->pendingPayment();

        $this->verify($b->id, 'order_open', 6)->assertStatus(404);
        $this->verify($b->id, 'order_other', 5)->assertStatus(400)->assertJsonPath('message', 'Payment order does not match this booking');
    }

    public function test_a_payment_survives_a_shipment_failure_and_the_shipment_can_be_retried(): void
    {
        [$b] = $this->pendingPayment();
        $this->sr->orderFails = self::sr(ShiprocketException::SERVER_ERROR, 500);

        $this->verify($b->id, 'order_open')->assertOk()->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.shipment', null);

        $this->sr->orderFails = null;
        $this->sr->existingOrder = ['order_id' => '9001', 'shipment_id' => '7001', 'awb_code' => null, 'courier_name' => 'Delhivery'];   // the first request did create it
        $this->sr->calls = [];
        $this->me()->postJson("/service/courier/bookings/{$b->id}/shiprocket")->assertOk()->assertJsonPath('message', 'Shipment created and pickup requested.');
        $this->assertSame(['find', 'awb', 'pickup'], $this->sr->calls);   // it looked the order up instead of creating a second one
    }

    // ------------------------------------------------------------------ shipment steps

    public function test_the_shipment_needs_a_paid_booking_and_server_config(): void
    {
        $b = $this->cdb->shiprocketBooking(['payment_status' => 'initiated']);
        $this->me()->postJson("/service/courier/bookings/{$b->id}/shiprocket")->assertStatus(409)->assertJsonPath('message', 'Payment has not been completed for this booking.');

        $paid = $this->cdb->paidBooking();
        config(['shiprocket.pickup_location' => null]);
        $this->me()->postJson("/service/courier/bookings/{$paid->id}/shiprocket")->assertStatus(503);
        $this->assertSame([], $this->sr->calls);
    }

    public function test_a_refused_order_or_a_failed_awb_reports_what_is_left_to_do(): void
    {
        $b = $this->cdb->paidBooking();
        $this->sr->orderFails = self::sr(ShiprocketException::CLIENT_ERROR, 422);
        $this->me()->postJson("/service/courier/bookings/{$b->id}/shiprocket")->assertStatus(422)->assertJsonPath('message', 'The shipment could not be created. Please contact support.');

        $this->sr->orderFails = null;
        $this->sr->awbFails = self::sr(ShiprocketException::CLIENT_ERROR, 200);
        $this->me()->postJson("/service/courier/bookings/{$b->id}/shiprocket")->assertOk()
            ->assertJsonPath('data.action_required', 'awb')->assertJsonPath('data.shiprocket_order_id', '9001')
            ->assertJsonPath('message', 'Shipment created. Courier assignment is pending, please retry shortly.');

        $this->sr->awbFails = null;
        $this->sr->calls = [];
        $this->me()->postJson("/service/courier/bookings/{$b->id}/shiprocket")->assertOk()->assertJsonPath('data.action_required', null);
        $this->assertSame(['awb', 'pickup'], $this->sr->calls);   // the order is not created again
    }

    // ------------------------------------------------------------------ K8 history, detail, cancel, track, invoice

    public function test_history_and_detail_are_the_customers_own(): void
    {
        $b = $this->cdb->paidBooking();
        $this->cdb->booking(['user_id' => 6]);

        $this->me()->getJson('/service/courier/bookings')->assertOk()->assertJsonCount(1, 'data.bookings')
            ->assertJsonPath('data.bookings.0.booking_number', $b->booking_number)->assertJsonPath('data.bookings.0.shipment_status_label', 'Pending');

        $this->me()->getJson("/service/courier/bookings/{$b->id}")->assertOk()
            ->assertJsonPath('data.pricing.shiprocket_cost', 80)->assertJsonPath('data.pricing.paid_amount', 100)->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath('data.can_cancel', true)->assertJsonPath('data.courier.name', 'Delhivery');
        $this->me(6)->getJson("/service/courier/bookings/{$b->id}")->assertStatus(404)->assertJsonPath('message', 'Courier booking not found');
    }

    public function test_cancelling_before_pickup_cancels_at_shiprocket_and_refunds_in_full(): void
    {
        $b = $this->cdb->paidBooking();
        $this->me()->postJson("/service/courier/bookings/{$b->id}/shiprocket");
        $this->sr->calls = [];

        $this->me()->postJson("/service/courier/bookings/{$b->id}/cancel", ['reason' => 'Changed my mind'])->assertOk()
            ->assertJsonPath('message', 'Booking cancelled and refund processed.')->assertJsonPath('data.booking_status', 'cancelled')
            ->assertJsonPath('data.shipment_status', 'cancelled')->assertJsonPath('data.refund_status', 'success')->assertJsonPath('data.refund_amount', 100);

        $this->assertSame(['cancel'], $this->sr->calls);
        $this->assertSame(['pay_c1', 10000, 'courier'], [$this->razorpay->refunds[0][0], $this->razorpay->refunds[0][1], $this->razorpay->refunds[0][2]['type']]);

        // a repeated request changes nothing: no second Shiprocket call, no second refund
        $this->me()->postJson("/service/courier/bookings/{$b->id}/cancel")->assertOk()->assertJsonPath('data.refund_status', 'success');
        $this->assertSame(['cancel'], $this->sr->calls);
        $this->assertCount(1, $this->razorpay->refunds);
    }

    public function test_the_refund_policy_can_keep_the_service_charge(): void
    {
        config(['shiprocket.refund_policy' => 'exclude_platform_charge']);
        $b = $this->cdb->paidBooking();

        $this->me()->postJson("/service/courier/bookings/{$b->id}/cancel")->assertOk()->assertJsonPath('data.refund_amount', 80);
        $this->assertSame(8000, $this->razorpay->refunds[0][1]);
    }

    public function test_cancelling_is_refused_without_a_policy_after_pickup_or_when_shiprocket_refuses(): void
    {
        $b = $this->cdb->paidBooking();
        config(['shiprocket.refund_policy' => null]);
        $this->me()->postJson("/service/courier/bookings/{$b->id}/cancel")->assertStatus(503);
        $this->assertSame('paid', $b->status);

        config(['shiprocket.refund_policy' => 'full']);
        $picked = $this->cdb->paidBooking(['shipment_status' => 'picked_up']);
        $this->me()->postJson("/service/courier/bookings/{$picked->id}/cancel")->assertStatus(409)->assertJsonPath('message', 'Cancellation is no longer available for this shipment.');

        $this->me()->postJson("/service/courier/bookings/{$b->id}/shiprocket");
        $this->sr->cancelFails = self::sr(ShiprocketException::CLIENT_ERROR, 400);
        $this->me()->postJson("/service/courier/bookings/{$b->id}/cancel")->assertStatus(409);
        $this->assertSame('paid', $b->status);   // nothing local changes and nothing is refunded
        $this->assertSame([], $this->razorpay->refunds);

        $this->sr->cancelFails = self::sr(ShiprocketException::SERVER_ERROR, 500);
        $this->me()->postJson("/service/courier/bookings/{$b->id}/cancel")->assertStatus(503);
        $this->me(6)->postJson("/service/courier/bookings/{$b->id}/cancel")->assertStatus(404);
    }

    public function test_a_failed_refund_keeps_the_cancellation_and_is_resumed_by_the_next_call(): void
    {
        $b = $this->cdb->paidBooking();
        $this->razorpay->failRefund = true;

        $this->me()->postJson("/service/courier/bookings/{$b->id}/cancel")->assertOk()
            ->assertJsonPath('data.booking_status', 'cancelled')->assertJsonPath('data.refund_status', 'failed')
            ->assertJsonPath('message', 'Booking cancelled. The refund could not be completed yet and will be retried.');

        $this->razorpay->failRefund = false;
        $this->me()->postJson("/service/courier/bookings/{$b->id}/cancel")->assertOk()->assertJsonPath('data.refund_status', 'success');
        $this->assertCount(1, $this->razorpay->refunds);
    }

    public function test_an_existing_razorpay_refund_is_adopted_instead_of_refunding_twice(): void
    {
        $b = $this->cdb->paidBooking();
        $this->razorpay->existingRefunds = [['id' => 'rfnd_old', 'status' => 'processed', 'amount' => 10000, 'notes' => ['reference' => 'CRF'.$b->id.'P'.array_key_last($this->cdb->payments)]]];

        $this->me()->postJson("/service/courier/bookings/{$b->id}/cancel")->assertOk()->assertJsonPath('data.refund_status', 'success');
        $this->assertSame([], $this->razorpay->refunds);
    }

    public function test_tracking_stores_new_events_once_and_moves_the_status_forward(): void
    {
        $b = $this->cdb->paidBooking(['shipment_status' => 'pickup_requested']);
        $this->cdb->shipments[1] = (new \App\Models\CourierShipment)->forceFill(['id' => 1, 'courier_booking_id' => $b->id, 'awb_code' => 'AWB123', 'shipment_status' => 'pickup_requested']);
        $this->sr->events = [
            ['label' => 'PICKED UP', 'description' => 'Picked up', 'location' => 'Ahmedabad', 'time' => Carbon::parse('2026-01-02 10:00:00')],
            ['label' => 'IN TRANSIT', 'description' => 'On the way', 'location' => 'Mumbai', 'time' => Carbon::parse('2026-01-03 09:00:00')],
        ];

        $this->me()->postJson("/service/courier/bookings/{$b->id}/track")->assertOk()
            ->assertJsonPath('data.stored', 2)->assertJsonPath('data.status', 'in_transit')
            ->assertJsonPath('data.booking.tracking_timeline.1.title', 'In Transit')->assertJsonPath('data.booking.tracking_url', 'https://track.test/AWB123');
        $this->assertSame('in_transit', $b->shipment_status);

        $this->me()->postJson("/service/courier/bookings/{$b->id}/track")->assertOk()->assertJsonPath('data.stored', 0)->assertJsonPath('data.duplicates', 2);

        $this->sr->trackFails = self::sr(ShiprocketException::SERVER_ERROR, 500);
        $this->me()->postJson("/service/courier/bookings/{$b->id}/track")->assertStatus(503);
    }

    public function test_tracking_never_moves_a_delivered_shipment_and_needs_an_awb(): void
    {
        $b = $this->cdb->paidBooking();
        $this->me()->postJson("/service/courier/bookings/{$b->id}/track")->assertOk()->assertJsonPath('data.stored', 0);   // no shipment yet
        $this->assertSame([], $this->sr->calls);

        $d = $this->cdb->paidBooking(['shipment_status' => 'delivered']);
        $this->cdb->shipments[2] = (new \App\Models\CourierShipment)->forceFill(['id' => 2, 'courier_booking_id' => $d->id, 'awb_code' => 'AWB9', 'shipment_status' => 'delivered']);
        $this->me()->postJson("/service/courier/bookings/{$d->id}/track")->assertOk()->assertJsonPath('data.status', 'delivered');
        $this->assertSame([], $this->sr->calls);
    }

    public function test_the_invoice_is_only_for_a_paid_booking_of_the_customer(): void
    {
        $this->app->instance(SettingRepositoryInterface::class, new \Tests\Support\FakeSettingRepository);
        $unpaid = $this->cdb->shiprocketBooking();
        $paid = $this->cdb->paidBooking();

        $this->me()->getJson("/service/courier/bookings/{$unpaid->id}/invoice")->assertStatus(422);
        $this->me()->get("/service/courier/bookings/{$paid->id}/invoice")->assertOk()->assertSee('INV-'.$paid->booking_number)->assertSee('Courier shipping charge')->assertSee('80.00')->assertSee('20.00');
        $this->me(6)->getJson("/service/courier/bookings/{$paid->id}/invoice")->assertStatus(404);
    }
}
