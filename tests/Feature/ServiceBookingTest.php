<?php

namespace Tests\Feature;

use App\Repositories\Contracts\ServiceBookingRepositoryInterface;
use App\Repositories\Contracts\ServiceRefundRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Payments\RazorpayGateway;
use Tests\OrderTestCase;
use Tests\Support\FakeServiceBookingRepository;
use Tests\Support\FakeServiceDb;
use Tests\Support\FakeServiceRefundRepository;

require_once __DIR__.'/../Support/ServiceFakes.php';

class ServiceBookingTest extends OrderTestCase
{
    private FakeServiceDb $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = new FakeServiceDb;
        $this->svc->service(1, ['service_charge' => 500]);
        $this->app->instance(ServiceBookingRepositoryInterface::class, new FakeServiceBookingRepository($this->svc));
        $this->app->instance(ServiceRefundRepositoryInterface::class, new FakeServiceRefundRepository($this->svc));
    }

    private function body(array $extra = []): array
    {
        return $extra + ['service_id' => 1, 'name' => 'Asha', 'mobile' => '9876543210', 'address_line_1' => '12 MG Road', 'pincode' => '380001', 'city' => 'Ahmedabad'];
    }

    private function book(array $extra = [], int $user = 5)
    {
        return $this->actingAs($this->customer($user))->postJson('/service/book', $this->body($extra));
    }

    /** A pending booking with its Razorpay order, optionally already paid. */
    private function booking(string $payment = 'pending', array $attrs = [], int $user = 5): \App\Models\ServiceMarketplaceBooking
    {
        $b = $this->svc->booking($attrs + ['user_id' => $user, 'payment_status' => $payment]);
        $p = $this->svc->payment($b, $payment === 'paid' ? ['status' => 'paid', 'gateway_payment_id' => 'pay_1', 'gateway_order_id' => 'order_paid'] : ['gateway_order_id' => 'order_open']);
        $b->paid_at = $payment === 'paid' ? now() : null;
        $b->setRelation('latestPayment', $p);

        return $b;
    }

    // ------------------------------------------------------------------ K1 catalogue

    public function test_the_catalogue_is_public_and_lists_only_active_services(): void
    {
        $this->svc->service(2, ['is_active' => false]);

        $this->getJson('/service/services')->assertOk()->assertJsonPath('message', 'Services fetched successfully')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Service 1')
            ->assertJsonPath('data.0.service_charge', 500)->assertJsonPath('data.0.base_price', 500);
    }

    // ------------------------------------------------------------------ K2 book

    public function test_booking_needs_a_login_and_valid_details(): void
    {
        $this->postJson('/service/book', $this->body())->assertStatus(401);
        $this->actingAs($this->customer())->postJson('/service/book', ['service_id' => 1])->assertStatus(400);
        $this->actingAs($this->customer())->postJson('/service/book', $this->body(['pincode' => '12']))->assertStatus(400)->assertJsonPath('success', false);
        $this->actingAs($this->customer())->postJson('/service/book', $this->body(['scheduled_for' => '2020-01-01 10:00']))->assertStatus(400);
    }

    public function test_a_booking_creates_a_pending_booking_and_a_razorpay_order_for_the_service_charge(): void
    {
        $this->book(['customer_notes' => 'Ring the bell'])->assertStatus(201)
            ->assertJsonPath('message', 'Booking created. Continue to payment.')
            ->assertJsonPath('data.amount', 500)->assertJsonPath('data.amount_in_paise', 50000)->assertJsonPath('data.razorpay_key', 'rzp_test_key')
            ->assertJsonPath('data.razorpay_order_id', 'order_rzp_1')->assertJsonPath('data.booking.status', 'pending')->assertJsonPath('data.booking.payment_status', 'pending')
            ->assertJsonPath('data.booking.service.name', 'Service 1');

        $this->assertSame(50000, $this->razorpay->created[0][0]);
        $this->assertSame('service', $this->razorpay->created[0][1]['type']);
        $this->assertSame('Booking created', $this->svc->histories[0]->reason);
    }

    public function test_the_same_request_resumes_the_booking_instead_of_duplicating_it(): void
    {
        $this->book(['idempotency_key' => 'k1'])->assertStatus(201);
        $this->book(['idempotency_key' => 'k1'])->assertOk()->assertJsonPath('message', 'Booking already exists for this request.')->assertJsonPath('data.razorpay_order_id', 'order_rzp_1');

        $this->assertCount(1, $this->svc->bookings);
        $this->assertCount(1, $this->razorpay->created);
    }

    public function test_nothing_is_created_when_the_area_is_not_served_or_the_charge_is_missing(): void
    {
        $this->svc->providers = 0;
        $this->book()->assertStatus(400)->assertJsonPath('message', 'Service is not available in your area.')->assertJsonPath('data.available', false);

        $this->svc->providers = 1;
        $this->svc->assignable = false;
        $this->book()->assertStatus(400)->assertJsonPath('message', 'Service is currently unavailable.');

        $this->svc->assignable = true;
        $this->svc->service(1, ['service_charge' => 0]);
        $this->book()->assertStatus(400)->assertJsonPath('message', 'Service charge is not set for this service.');

        $this->book(['service_id' => 99])->assertStatus(404)->assertJsonPath('message', 'Service not found.');
        $this->assertSame([], $this->svc->bookings);
        $this->assertSame([], $this->razorpay->created);
    }

    public function test_a_saved_address_must_belong_to_the_customer(): void
    {
        $this->svc->addresses[7] = (new \App\Models\ServiceAddress)->forceFill(['id' => 7, 'user_id' => 6, 'address_line_1' => 'x', 'pincode' => '380001']);

        $this->actingAs($this->customer(5))->postJson('/service/book', ['service_id' => 1, 'address_id' => 7])->assertStatus(403)->assertJsonPath('message', 'Selected address does not belong to you.');
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer(6))->postJson('/service/book', ['service_id' => 1, 'address_id' => 7])->assertStatus(201);
    }

    public function test_a_payment_gateway_failure_is_reported_and_leaves_no_payment(): void
    {
        $this->razorpay->failCreate = true;

        $this->book()->assertStatus(502)->assertJsonPath('message', 'Unable to initiate payment.');
        $this->assertSame([], $this->svc->payments);
    }

    // ------------------------------------------------------------------ K3 payment verification

    private function verify(string $order = 'order_open', int $user = 5)
    {
        return $this->actingAs($this->customer($user))->postJson('/service/book/verify', ['razorpay_order_id' => $order, 'razorpay_payment_id' => 'pay_1', 'razorpay_signature' => 'sig']);
    }

    public function test_a_verified_payment_marks_the_booking_paid_and_the_admin_confirms_later(): void
    {
        $b = $this->booking();
        $this->razorpay->remote = ['id' => 'pay_1', 'order_id' => 'order_open', 'amount' => 50000, 'currency' => 'INR', 'status' => 'captured', 'method' => 'upi'];

        $this->verify()->assertOk()->assertJsonPath('message', 'Payment verified successfully')
            ->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.status', 'pending')->assertJsonPath('data.booking_number', $b->booking_number);

        $this->assertSame('paid', $this->svc->payments[array_key_last($this->svc->payments)]->status);
        $this->verify()->assertOk()->assertJsonPath('message', 'Payment already verified');
    }

    public function test_a_bad_signature_or_a_mismatching_payment_is_not_accepted(): void
    {
        $b = $this->booking();

        $this->razorpay->signatureOk = false;
        $this->verify()->assertStatus(400)->assertJsonPath('message', 'Payment verification failed.');

        $this->razorpay->signatureOk = true;
        $this->razorpay->remote = ['id' => 'pay_1', 'order_id' => 'order_open', 'amount' => 100, 'currency' => 'INR', 'status' => 'captured'];   // wrong amount
        $this->verify()->assertStatus(400);

        $this->razorpay->remote = null;   // Razorpay unreachable
        $this->verify()->assertStatus(400);

        $this->assertSame('pending', $b->payment_status);
    }

    public function test_someone_elses_payment_looks_like_a_missing_one(): void
    {
        $this->booking('pending', [], 6);

        $this->verify('order_open', 5)->assertStatus(404)->assertJsonPath('message', 'Payment not found.');
        $this->verify('order_nope', 5)->assertStatus(404);
    }

    // ------------------------------------------------------------------ K4 history, detail, cancel, invoice

    public function test_history_is_the_customers_own_and_filters_by_category(): void
    {
        $this->booking('pending');
        $this->booking('paid', ['status' => 'confirmed']);
        $this->booking('pending', [], 6);

        $this->actingAs($this->customer(5))->getJson('/service/book')->assertOk()->assertJsonCount(2, 'data.bookings')->assertJsonPath('data.pagination.total', 2);
        $this->actingAs($this->customer(5))->getJson('/service/book?category=upcoming')->assertJsonCount(1, 'data.bookings')->assertJsonPath('data.bookings.0.status', 'confirmed');
        $this->actingAs($this->customer(5))->getJson('/service/book?category=bogus')->assertStatus(400);
    }

    public function test_detail_by_id_or_number_with_timeline_and_provider_rules(): void
    {
        $b = $this->booking('paid', ['status' => 'provider_assigned']);
        $b->setRelation('provider', (new \App\Models\ServiceProvider)->forceFill(['profile_image' => null])->setRelation('user', (new \App\Models\User)->forceFill(['name' => 'Ravi', 'mobile' => '9000000000'])));
        $this->svc->bookings[$b->id] = $b;
        (new \ReflectionMethod($this->app->make(ServiceBookingRepositoryInterface::class), 'recordHistory'))->invoke($this->app->make(ServiceBookingRepositoryInterface::class), $b, null, 'pending', 'customer', 5, 'Booking created');

        $res = $this->actingAs($this->customer(5))->getJson('/service/book/'.$b->booking_number)->assertOk()
            ->assertJsonPath('data.booking_number', $b->booking_number)->assertJsonPath('data.timeline.0.title', 'Booking created')
            ->assertJsonPath('data.provider.name', 'Ravi')->assertJsonPath('data.provider.mobile', null)   // number only after the provider accepts
            ->assertJsonPath('data.can_cancel', true)->assertJsonPath('data.payment.paid_amount', 500);
        $this->assertNotNull($res->json('data.timestamps.created_at'));

        $b->status = 'provider_accepted';
        $this->actingAs($this->customer(5))->getJson('/service/book/'.$b->id)->assertJsonPath('data.provider.mobile', '9000000000');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer(6))->getJson('/service/book/'.$b->id)->assertStatus(404)->assertJsonPath('message', 'Booking not found.');
    }

    public function test_cancelling_an_unpaid_booking_needs_no_refund(): void
    {
        $b = $this->booking('pending');

        $this->actingAs($this->customer(5))->postJson("/service/book/{$b->id}/cancel", ['reason' => 'Changed my mind'])->assertOk()
            ->assertJsonPath('message', 'Booking cancelled successfully.')->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.refund_amount', 0)->assertJsonPath('data.refund_status', 'not_required');
        $this->assertSame([], $this->razorpay->refunds);
        $this->actingAs($this->customer(5))->postJson("/service/book/{$b->id}/cancel")->assertStatus(409)->assertJsonPath('message', 'Booking is already cancelled.');
    }

    public function test_cancelling_a_paid_booking_refunds_by_the_services_policy_once(): void
    {
        $b = $this->booking('paid', ['status' => 'confirmed']);   // 50% of 500

        $this->actingAs($this->customer(5))->postJson("/service/book/{$b->booking_number}/cancel", ['reason' => 'Not needed'])->assertOk()
            ->assertJsonPath('data.refund_amount', 250)->assertJsonPath('data.refund_status', 'success');

        $this->assertCount(1, $this->razorpay->refunds);
        [$payId, $paise, $notes] = $this->razorpay->refunds[0];
        $this->assertSame(['pay_1', 25000, 'service'], [$payId, $paise, $notes['type']]);
        $this->assertSame('partially_refunded', $this->svc->payments[array_key_last($this->svc->payments)]->status);   // 250 of 500 came back
        $this->assertSame('refund_success', collect($this->svc->histories)->last()->to_status);
    }

    public function test_a_gateway_failure_keeps_the_cancellation_and_marks_the_refund_failed(): void
    {
        $b = $this->booking('paid', ['status' => 'confirmed']);
        $this->razorpay->failRefund = true;

        $this->actingAs($this->customer(5))->postJson("/service/book/{$b->id}/cancel")->assertOk()
            ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.refund_amount', 250)->assertJsonPath('data.refund_status', 'failed');
    }

    public function test_a_started_booking_or_a_broken_refund_policy_cannot_be_cancelled(): void
    {
        $started = $this->booking('paid', ['status' => 'in_progress']);
        $this->actingAs($this->customer(5))->postJson("/service/book/{$started->id}/cancel")->assertStatus(409)->assertJsonPath('message', 'This booking can no longer be cancelled.');

        $broken = $this->booking('paid', ['status' => 'confirmed', 'refund_type' => null, 'refund_value' => null]);
        $this->actingAs($this->customer(5))->postJson("/service/book/{$broken->id}/cancel")->assertStatus(400);
        $this->assertSame('confirmed', $broken->status);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer(6))->postJson("/service/book/{$broken->id}/cancel")->assertStatus(404);
    }

    public function test_the_invoice_is_only_for_a_paid_booking_of_the_customer(): void
    {
        $this->app->instance(SettingRepositoryInterface::class, new \Tests\Support\FakeSettingRepository);
        $unpaid = $this->booking('pending');
        $paid = $this->booking('paid');

        $this->actingAs($this->customer(5))->getJson("/service/book/{$unpaid->id}/invoice")->assertStatus(422)->assertJsonPath('message', 'Invoice is available once the payment is completed.');
        $this->actingAs($this->customer(5))->get("/service/book/{$paid->booking_number}/invoice")->assertOk()->assertSee('INV-'.$paid->booking_number)->assertSee('500.00');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer(6))->getJson("/service/book/{$paid->id}/invoice")->assertStatus(404);
    }

    public function test_everything_but_the_catalogue_needs_a_login(): void
    {
        foreach (['/service/book', '/service/book/1', '/service/book/1/invoice'] as $path) {
            $this->getJson($path)->assertStatus(401);
        }
        $this->postJson('/service/book/verify', [])->assertStatus(401);
        $this->postJson('/service/book/1/cancel')->assertStatus(401);
    }
}
