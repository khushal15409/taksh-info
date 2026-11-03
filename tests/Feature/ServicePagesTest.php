<?php

namespace Tests\Feature;

use App\Models\CourierDeliveryPartner;
use App\Repositories\Contracts\AddressRepositoryInterface;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use App\Repositories\Contracts\CourierShipmentRepositoryInterface;
use App\Repositories\Contracts\ServiceBookingRepositoryInterface;
use App\Repositories\Contracts\ServiceRefundRepositoryInterface;
use App\Services\Courier\CourierPhotoStore;
use App\Services\Shiprocket\ShiprocketApi;
use Illuminate\Http\UploadedFile;
use Tests\OrderTestCase;
use Tests\Support\FakeAddressRepository;
use Tests\Support\FakeCourierBookingRepository;
use Tests\Support\FakeCourierDb;
use Tests\Support\FakeCourierPhotoStore;
use Tests\Support\FakeCourierShipmentRepository;
use Tests\Support\FakeServiceBookingRepository;
use Tests\Support\FakeServiceDb;
use Tests\Support\FakeServiceRefundRepository;
use Tests\Support\FakeShiprocketApi;
use Tests\Support\Make;

require_once __DIR__.'/../Support/ServiceFakes.php';
require_once __DIR__.'/../Support/CourierFakes.php';
require_once __DIR__.'/../Support/AccountFakes.php';

/** Phase 7: home services and courier, rendered by the server - catalogue, booking form, booking pages, courier form, history, cancel, pay buttons. */
class ServicePagesTest extends OrderTestCase
{
    private const KEY = '3f2504e0-4f89-41d3-9a0c-0305e82c3301';

    private FakeServiceDb $svc;

    private FakeCourierDb $cdb;

    private FakeShiprocketApi $sr;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shiprocket.pickup_location' => 'Primary', 'shiprocket.default_declared_value' => 500, 'shiprocket.refund_policy' => 'full']);

        $this->svc = new FakeServiceDb;
        $this->svc->service(1, ['service_charge' => 500, 'short_description' => 'Cooling fixed']);
        $this->app->instance(ServiceBookingRepositoryInterface::class, new FakeServiceBookingRepository($this->svc));
        $this->app->instance(ServiceRefundRepositoryInterface::class, new FakeServiceRefundRepository($this->svc));

        $this->cdb = new FakeCourierDb;
        $this->app->instance(CourierBookingRepositoryInterface::class, new FakeCourierBookingRepository($this->cdb));
        $this->app->instance(CourierShipmentRepositoryInterface::class, new FakeCourierShipmentRepository($this->cdb));
        $this->app->instance(ShiprocketApi::class, $this->sr = new FakeShiprocketApi);
        $this->app->instance(CourierPhotoStore::class, new FakeCourierPhotoStore);

        $book = new FakeAddressRepository;
        $book->seed(5, ['is_default' => true, 'name' => 'Asha Shah', 'address_line_1' => '12 Ring Road', 'pincode' => '380001']);
        $this->app->instance(AddressRepositoryInterface::class, $book);
    }

    private function me(int $id = 5)
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($this->customer($id));
    }

    private function bookingBody(array $extra = []): array
    {
        return $extra + ['idempotency_key' => self::KEY, 'name' => 'Asha', 'mobile' => '9876543210', 'address_line_1' => '12 MG Road', 'pincode' => '380001', 'city' => 'Ahmedabad'];
    }

    /** A booking of the customer, with its Razorpay order (paid or waiting). */
    private function booking(string $payment = 'pending', array $attrs = [], int $user = 5): \App\Models\ServiceMarketplaceBooking
    {
        $b = $this->svc->booking($attrs + ['user_id' => $user, 'payment_status' => $payment, 'address_snapshot' => ['name' => 'Asha', 'mobile' => '9876543210', 'address_line_1' => '12 MG Road', 'city' => 'Ahmedabad', 'pincode' => '380001']]);
        $p = $this->svc->payment($b, $payment === 'paid' ? ['status' => 'paid', 'gateway_payment_id' => 'pay_1', 'gateway_order_id' => 'order_paid'] : ['gateway_order_id' => 'order_open']);
        $b->paid_at = $payment === 'paid' ? now() : null;
        $b->setRelation('latestPayment', $p);

        return $b;
    }

    // ------------------------------------------------------------------ the services page

    public function test_the_catalogue_is_open_to_everyone(): void
    {
        $this->svc->service(2, ['service_charge' => 0, 'name' => 'Free Quote Service']);
        $this->svc->service(3, ['is_active' => false, 'name' => 'Hidden']);

        $this->get('/services')->assertOk()
            ->assertSee('Expert Services at Your Doorstep')->assertSee('Service 1')->assertSee('Cooling fixed')->assertSee('₹500')->assertSee('/services/1/book', false)->assertSee('Book Now')
            ->assertSee('Free Quote Service')->assertSee('Price on request')->assertSee('Unavailable')->assertDontSee('Hidden')
            ->assertDontSee('My Service Bookings')->assertSee('id="courier-form"', false)->assertSee('Log in to book a courier')->assertDontSee('id="services-grid"', false);
    }

    public function test_service_banners_and_the_faq_are_shown(): void
    {
        $this->banners->banners = collect([Make::banner(1, ['type' => 'service', 'title' => 'AC Offer', 'image_url' => 'https://cdn.test/ac.jpg'])]);

        $this->get('/services')->assertSee('https://cdn.test/ac.jpg', false)->assertSee('How are service professionals verified?')->assertSee('care@takshallinone.com');
    }

    public function test_my_bookings_are_the_customers_own_with_tabs_and_status(): void
    {
        $a = $this->booking('pending');
        $b = $this->booking('paid', ['status' => 'confirmed']);
        $c = $this->booking('pending', [], 6);

        $this->me()->get('/services')->assertSee('My Service Bookings')->assertSee('/services/bookings/', false)->assertSee('Pending')->assertSee('Confirmed')->assertSee('#'.$a->booking_number)->assertDontSee('#'.$c->booking_number);
        $this->me()->get('/services?category=upcoming')->assertSee('#'.$b->booking_number)->assertDontSee('#'.$a->booking_number);
        $this->me()->get('/services?category=bogus')->assertOk()->assertSee('#'.$a->booking_number);
        $this->me(9)->get('/services')->assertSee('No bookings here yet.');
    }

    // ------------------------------------------------------------------ the booking form + creating a booking

    public function test_the_booking_form_is_prefilled_from_the_default_address(): void
    {
        $this->me()->get('/services/1/book')->assertOk()
            ->assertSee('Book Service 1')->assertSee('₹500')->assertSee('value="Asha Shah"', false)->assertSee('value="12 Ring Road"', false)->assertSee('value="380001"', false)
            ->assertSee('name="idempotency_key"', false)->assertSee('Pay ₹500 &amp; book', false);
        $this->me()->get('/services/99/book')->assertNotFound()->assertSee('Service not found');
        $this->app['auth']->forgetGuards();
        $this->get('/services/1/book')->assertRedirect('/login?next=%2Fservices%2F1%2Fbook');
    }

    public function test_booking_creates_the_booking_and_the_razorpay_order_and_lands_on_the_pay_page(): void
    {
        $this->me()->post('/services/1/book', $this->bookingBody(['customer_notes' => 'Ring the bell']));
        $id = array_key_last($this->svc->bookings);
        $b = $this->svc->bookings[$id];
        $this->assertSame(['pending', 'pending', 500.0], [$b->status, $b->payment_status, (float) $b->service_charge]);
        $this->assertSame(50000, $this->razorpay->created[0][0]);

        $this->me()->get("/services/bookings/$id?pay=1")->assertOk()
            ->assertSee('Payment pending')->assertSee('id="pay-now"', false)->assertSee('data-order="order_rzp_1"', false)->assertSee('data-amount="50000"', false)->assertSee('data-key="rzp_test_key"', false)->assertSee('data-start="1"', false)
            ->assertSee('assets/js/modules/service-pay.js', false)->assertSee('Booking created');
    }

    public function test_pressing_book_twice_makes_one_booking(): void
    {
        $first = $this->me()->post('/services/1/book', $this->bookingBody());
        $id = array_key_last($this->svc->bookings);
        $first->assertRedirect("/services/bookings/$id?pay=1");
        $this->me()->post('/services/1/book', $this->bookingBody())->assertRedirect("/services/bookings/$id?pay=1");   // the same page, the same key

        $this->assertCount(1, $this->svc->bookings);
        $this->assertCount(1, $this->razorpay->created);
    }

    public function test_a_bad_booking_form_comes_back_with_errors_under_the_fields(): void
    {
        $this->me()->from('/services/1/book')->post('/services/1/book', $this->bookingBody(['mobile' => '123', 'pincode' => '12', 'name' => '', 'scheduled_for' => '2020-01-01T10:00']))->assertRedirect('/services/1/book');

        $this->me()->get('/services/1/book')->assertSee('Enter a valid 10-digit mobile number')->assertSee('Enter a valid 6-digit pincode')->assertSee('Pick a time in the future')->assertSee('border-red-400', false)->assertSee('value="123"', false);
        $this->assertSame([], $this->svc->bookings);
        $this->me()->from('/services/1/book')->post('/services/1/book', $this->bookingBody(['idempotency_key' => 'nope']))->assertRedirect('/services/1/book');
        $this->me()->get('/services/1/book')->assertSee('This page expired');
    }

    public function test_an_area_without_a_provider_creates_nothing_and_says_why(): void
    {
        $this->svc->providers = 0;

        $this->me()->post('/services/1/book', $this->bookingBody())->assertRedirect('/services/1/book');
        $this->me()->get('/services/1/book')->assertSee('Service is not available in your area.')->assertSee('value="12 MG Road"', false);   // what was typed is kept
        $this->assertSame([], $this->svc->bookings);
        $this->assertSame([], $this->razorpay->created);
    }

    public function test_a_payment_gateway_failure_is_reported(): void
    {
        $this->razorpay->failCreate = true;

        $this->me()->post('/services/1/book', $this->bookingBody())->assertRedirect('/services/1/book');
        $this->me()->get('/services/1/book')->assertSee('Unable to initiate payment.');
    }

    // ------------------------------------------------------------------ one booking

    public function test_a_paid_booking_shows_provider_rules_timeline_and_invoice(): void
    {
        $b = $this->booking('paid', ['status' => 'provider_assigned']);
        $b->setRelation('provider', (new \App\Models\ServiceProvider)->forceFill([])->setRelation('user', (new \App\Models\User)->forceFill(['name' => 'Ravi', 'mobile' => '9000000000'])));
        app(ServiceBookingRepositoryInterface::class)->recordHistory($b, null, 'pending', 'customer', 5, 'Booking created');

        $id = $b->id;
        $this->me()->get("/services/bookings/$id")->assertOk()
            ->assertSee('<title>Booking #'.$b->booking_number.' | TakshAllInOne</title>', false)->assertSee('12 MG Road')->assertSee('Ravi')->assertSee('Contact number is shared once they accept the job.')->assertDontSee('9000000000')
            ->assertSee('Booking created')->assertSee('Invoice')->assertSee("/service/book/$id/invoice", false)->assertSee('Cancel booking')->assertDontSee('id="pay-now"', false);

        $b->status = 'provider_accepted';
        $this->me()->get("/services/bookings/$id")->assertSee('tel:9000000000', false);
        $this->me()->get('/services/bookings/'.$b->booking_number)->assertOk()->assertSee('#'.$b->booking_number);   // by number too
    }

    public function test_someone_elses_or_a_missing_booking_is_not_shown(): void
    {
        $theirs = $this->booking('paid', [], 9);

        $this->me()->get('/services/bookings/'.$theirs->id)->assertNotFound()->assertSee('Booking not found');
        $this->me()->get('/services/bookings/999')->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->get('/services/bookings/'.$theirs->id)->assertRedirect('/login?next=%2Fservices%2Fbookings%2F'.$theirs->id);
    }

    public function test_a_failed_payment_can_be_tried_again_without_a_second_booking(): void
    {
        $b = $this->booking('failed', ['idempotency_key' => self::KEY]);
        $b->latestPayment->status = 'failed';
        $this->svc->payments[array_key_last($this->svc->payments)]->status = 'failed';

        $id = $b->id;
        $this->me()->get("/services/bookings/$id")->assertSee('Payment not completed')->assertSee('Pay again')->assertSee(route('services.bookings.repay', $id), false)->assertDontSee('id="pay-now"', false);

        $this->me()->post("/services/bookings/$id/pay")->assertRedirect("/services/bookings/$id?pay=1");
        $this->assertCount(1, $this->svc->bookings);
        $this->assertCount(1, $this->razorpay->created);                          // a fresh Razorpay order for the same booking
        $this->assertSame('pending', $b->payment_status);

        $paid = $this->booking('paid', ['status' => 'confirmed']);
        $this->me()->post('/services/bookings/'.$paid->id.'/pay')->assertRedirect('/services/bookings/'.$paid->id);
        $this->me()->get('/services/bookings/'.$paid->id)->assertSee('Booking already exists for this request.');
    }

    public function test_cancelling_an_unpaid_booking(): void
    {
        $b = $this->booking('pending');

        $this->me()->post("/services/bookings/{$b->id}/cancel", ['reason' => 'Changed my mind'])->assertRedirect("/services/bookings/{$b->id}");

        $this->assertSame('cancelled', $b->status);
        $this->me()->get("/services/bookings/{$b->id}")->assertSee('Booking cancelled.')->assertSee('Cancelled')->assertDontSee('Cancel booking</button>', false)->assertSee('Changed my mind');
        $this->me()->post("/services/bookings/{$b->id}/cancel")->assertRedirect();
        $this->me()->get("/services/bookings/{$b->id}")->assertSee('Booking is already cancelled.');
    }

    public function test_cancelling_a_paid_booking_refunds_by_the_services_policy(): void
    {
        $b = $this->booking('paid', ['status' => 'confirmed']);                         // 50% of 500

        $this->me()->post("/services/bookings/{$b->id}/cancel")->assertRedirect();

        $this->assertCount(1, $this->razorpay->refunds);
        $this->assertSame(25000, $this->razorpay->refunds[0][1]);
        $this->me()->get("/services/bookings/{$b->id}")->assertSee('Booking cancelled. Refund of ₹250 is done.');
    }

    public function test_a_started_or_someone_elses_booking_cannot_be_cancelled(): void
    {
        $started = $this->booking('paid', ['status' => 'in_progress']);
        $this->me()->post("/services/bookings/{$started->id}/cancel")->assertRedirect();
        $this->me()->get("/services/bookings/{$started->id}")->assertSee('This booking can no longer be cancelled.')->assertDontSee('Cancel booking</button>', false);

        $theirs = $this->booking('paid', ['status' => 'confirmed'], 9);
        $this->me()->post("/services/bookings/{$theirs->id}/cancel")->assertRedirect();
        $this->assertSame('confirmed', $theirs->status);
    }

    // ------------------------------------------------------------------ courier

    private function courierBody(array $extra = []): array
    {
        return $extra + [
            'customer_name' => 'Asha', 'customer_mobile' => '9876543210', 'pickup_address' => '1 Pickup Rd', 'pickup_pincode' => '380001', 'delivery_name' => 'Ravi', 'delivery_mobile' => '9123456780',
            'delivery_address' => '2 Drop St', 'delivery_pincode' => '400001', 'weight_grams' => 500, 'dimension_unit' => 'cm', 'length' => 10, 'width' => 10, 'height' => 10,
        ];
    }

    public function test_the_courier_form_asks_for_the_parcel_and_the_rate_lookup_is_a_small_call(): void
    {
        $this->me()->get('/services')->assertSee('Send parcels anywhere')->assertSee('Pickup pincode')->assertSee('Receiver mobile')->assertSee('Get delivery rates')->assertSee('multipart/form-data', false)
            ->assertSee('name="shiprocket_courier_company_id"', false)->assertSee('assets/js/modules/courier.js', false)->assertSee('Book pickup &amp; pay', false)->assertDontSee('Log in to book a courier');
    }

    public function test_booking_a_courier_creates_the_booking_and_goes_to_its_page(): void
    {
        $this->me()->post('/services/courier', $this->courierBody(['item_photo' => UploadedFile::fake()->image('parcel.jpg')]));
        $id = array_key_last($this->cdb->bookings);
        $b = $this->cdb->bookings[$id];
        $this->assertSame(['Delhivery', 100.0, 'initiated'], [$b->courier_name, (float) $b->total_amount, $b->payment_status]);   // the cheapest courier, priced on the server
        $this->assertSame(10000, $this->razorpay->created[0][0]);
        $this->assertNotNull($b->item_photo_path);

        $this->me()->get("/services/courier/$id?pay=1")->assertOk()
            ->assertSee('Courier #'.$b->booking_number)->assertSee('380001 → 400001')->assertSee('Delhivery')->assertSee('₹100')->assertSee('Payment pending')
            ->assertSee('id="pay-now"', false)->assertSee('data-kind="courier"', false)->assertSee('data-booking="'.$id.'"', false)->assertSee('data-start="1"', false)->assertSee('assets/js/modules/service-pay.js', false);
    }

    public function test_a_price_the_customer_was_shown_that_changed_is_caught(): void
    {
        $this->me()->post('/services/courier', $this->courierBody(['shiprocket_courier_company_id' => '12', 'quoted_rate' => 70]))->assertRedirect('/services#courier');

        $this->me()->get('/services')->assertSee('The courier price has changed. Please refresh the quote.')->assertSee('value="1 Pickup Rd"', false);
        $this->assertSame([], $this->cdb->bookings);
    }

    public function test_a_bad_courier_form_shows_the_errors_under_the_fields(): void
    {
        $this->me()->from('/services')->post('/services/courier', $this->courierBody(['customer_mobile' => '12345', 'pickup_pincode' => '12', 'weight_grams' => 0, 'delivery_name' => '']))->assertRedirect('/services#courier');

        $this->me()->get('/services')->assertSee('Enter a valid 10-digit mobile number')->assertSee('Enter a valid 6-digit pincode')->assertSee('This field is required')->assertSee('value="2 Drop St"', false)->assertSee('border-red-400', false);
        $this->assertSame([], $this->cdb->bookings);
    }

    public function test_courier_history_and_the_booking_page_belong_to_the_customer(): void
    {
        $paid = $this->cdb->paidBooking(['awb_code' => 'AWB123']);
        $other = $this->cdb->booking(['user_id' => 6]);

        $this->me()->get('/services')->assertSee('My Courier Bookings')->assertSee('/services/courier/'.$paid->id, false)->assertSee('AWB AWB123')->assertDontSee($other->booking_number);
        $this->me()->get('/services/courier/'.$paid->id)->assertOk()->assertSee('Invoice')->assertSee('/service/courier/bookings/'.$paid->id.'/invoice', false)->assertSee('Cancel')->assertDontSee('id="pay-now"', false);
        $this->me(6)->get('/services/courier/'.$paid->id)->assertNotFound()->assertSee('Courier booking not found');
    }

    public function test_the_courier_tracking_timeline_is_shown_and_can_be_refreshed(): void
    {
        $b = $this->cdb->paidBooking(['shipment_status' => 'pickup_requested', 'awb_code' => 'AWB123']);
        $this->cdb->shipments[1] = (new \App\Models\CourierShipment)->forceFill(['id' => 1, 'courier_booking_id' => $b->id, 'awb_code' => 'AWB123', 'shipment_status' => 'pickup_requested']);
        $this->sr->events = [['label' => 'PICKED UP', 'description' => 'Picked up', 'location' => 'Ahmedabad', 'time' => \Carbon\Carbon::parse('2026-01-02 10:00:00')]];

        $this->me()->get('/services/courier/'.$b->id)->assertSee('Tracking updates will appear here')->assertSee('Refresh tracking');
        $this->me()->post("/services/courier/{$b->id}/track")->assertRedirect("/services/courier/{$b->id}");
        $this->me()->get("/services/courier/{$b->id}")->assertSee('Tracking updated')->assertSee('Picked Up')->assertSee('Ahmedabad')->assertDontSee('Tracking updates will appear here');
    }

    public function test_cancelling_a_courier_before_pickup_refunds_it(): void
    {
        $b = $this->cdb->paidBooking();

        $this->me()->post("/services/courier/{$b->id}/cancel", ['reason' => 'Not needed'])->assertRedirect("/services/courier/{$b->id}");

        $this->assertSame(['cancelled', 10000], [$b->status, $this->razorpay->refunds[0][1]]);
        $this->me()->get("/services/courier/{$b->id}")->assertSee('Booking cancelled and refund processed.')->assertSee('Cancelled')->assertSee('Refund')->assertDontSee('data-modal-open="cancel-courier"', false);

        config(['shiprocket.refund_policy' => null]);
        $other = $this->cdb->paidBooking();
        $this->me()->post("/services/courier/{$other->id}/cancel")->assertRedirect();
        $this->me()->get("/services/courier/{$other->id}")->assertSee('Cancellation is temporarily unavailable.');
        $this->me(6)->post("/services/courier/{$other->id}/cancel")->assertRedirect();
        $this->assertSame('paid', $other->status);
    }

    public function test_the_actions_need_a_login(): void
    {
        $this->post('/services/1/book', [])->assertRedirect();
        $this->post('/services/courier', [])->assertRedirect();
        $this->post('/services/bookings/1/cancel')->assertRedirect();
        $this->post('/services/courier/1/cancel')->assertRedirect();
        $this->assertSame([], $this->svc->bookings);
    }

    // ------------------------------------------------------------------ demo mode untouched
}
