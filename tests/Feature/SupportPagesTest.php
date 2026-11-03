<?php

namespace Tests\Feature;

use App\Repositories\Contracts\SupportRequestRepositoryInterface;
use Tests\CatalogTestCase;
use Tests\Support\FakeSupportRequestRepository;
use Tests\Support\Make;

require_once __DIR__.'/../Support/SupportFakes.php';

/** Phase 7: the enquiry forms and the help page - ordinary server-validated forms. */
class SupportPagesTest extends CatalogTestCase
{
    private FakeSupportRequestRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SupportRequestRepositoryInterface::class, $this->repo = new FakeSupportRequestRepository);
    }

    private function delivery(array $extra = [])
    {
        return $this->post('/support/delivery-partner', $extra + ['name' => 'Ravi Patel', 'phone' => '+91 91234 56780', 'city' => 'Ahmedabad', 'pincode' => '380001', 'vehicle' => 'Motorbike', 'licence' => 'GJ01 2020 123456', 'shift' => 'Full time', 'agree' => '1']);
    }

    // ------------------------------------------------------------------ the pages

    public function test_each_form_page_shows_its_wording_and_fields(): void
    {
        $this->get('/join/delivery-partner')->assertOk()->assertSee('Join as Delivery Partner')->assertSee('Weekly payouts')->assertSee('Driving licence number')->assertSee('Motorbike')
            ->assertSee('action="'.route('support.delivery.store').'"', false)->assertSee('name="agree"', false)->assertDontSee('id="form-root"', false);
        $this->get('/join/vendor')->assertOk()->assertSee('Become a Vendor')->assertSee('What will you sell?')->assertSee('Register as Vendor')->assertSee('Groceries');
        $this->get('/development-request')->assertOk()->assertSee('Development Request')->assertSee('Create Website')->assertSee('Above ₹5,00,000')->assertSee('Send Request');
    }

    public function test_the_vendor_categories_come_from_the_catalogue(): void
    {
        $this->categories->tree = collect([Make::category(4, ['name' => 'Spices', 'parent_id' => null, 'delivery_types' => null])->setRelation('children', collect())]);

        $this->get('/join/vendor')->assertOk()->assertSee('Spices')->assertDontSee('Fresh produce');
    }

    public function test_a_customers_details_are_filled_in(): void
    {
        $me = $this->customer(5);
        $me->name = 'Asha Shah';
        $me->email = 'asha@example.com';

        $this->actingAs($me)->get('/development-request')->assertSee('value="Asha Shah"', false)->assertSee('value="9876543210"', false)->assertSee('value="asha@example.com"', false);
        $this->actingAs($me)->get('/join/vendor')->assertSee('value="Asha Shah"', false);
    }

    // ------------------------------------------------------------------ delivery partner

    public function test_a_delivery_partner_application_is_saved_and_thanked_once(): void
    {
        $this->delivery()->assertRedirect('/join/delivery-partner');

        $row = $this->repo->joins[0];
        $this->assertSame(['Ravi Patel', '9123456780', 'Ahmedabad', '380001', 'open'], [$row->name, $row->mobile, $row->address, $row->pincode, $row->status]);
        $this->assertSame('Vehicle: Motorbike. Licence: GJ01 2020 123456. Availability: Full time.', $row->description);

        $this->get('/join/delivery-partner')->assertSee('Thank you!')->assertSee('Application received!')->assertSee('Reference ID:')->assertSee('#1')->assertDontSee('name="agree"', false);
        $this->get('/join/delivery-partner')->assertDontSee('Thank you!')->assertSee('name="agree"', false);   // shown once
    }

    public function test_a_bad_delivery_form_shows_the_errors_under_the_fields_and_keeps_what_was_typed(): void
    {
        $this->from('/join/delivery-partner')->delivery(['phone' => '12345', 'pincode' => '12', 'name' => '', 'agree' => null, 'vehicle' => 'Rocket'])->assertRedirect('/join/delivery-partner');

        $this->get('/join/delivery-partner')->assertSee('Enter a valid mobile number')->assertSee('Enter a valid 6-digit pincode')->assertSee('This field is required')->assertSee('Please tick the box to continue')
            ->assertSee('value="12345"', false)->assertSee('border-red-400', false);
        $this->assertSame([], $this->repo->joins);
    }

    // ------------------------------------------------------------------ vendor

    private function vendor(array $extra = [])
    {
        return $this->post('/support/vendor', $extra + ['business' => 'Asha Stores', 'owner' => 'Asha Shah', 'phone' => '9123456780', 'email' => 'a@x.in', 'business_type' => 'product_vendor', 'category' => 'Groceries', 'gstin' => '24abcde1234f1z5', 'city' => 'Surat', 'pincode' => '395001', 'about' => 'Sarees and dress material', 'agree' => '1']);
    }

    public function test_a_vendor_request_is_saved_with_the_business_details(): void
    {
        $this->vendor()->assertRedirect('/join/vendor');

        $row = $this->repo->vendors[0];
        $this->assertSame(['Asha Shah', '9123456780', 'a@x.in', 'product_vendor', null], [$row->name, $row->mobile, $row->email, $row->business_type, $row->user_id]);
        $this->assertSame('Business: Asha Stores. Category: Groceries. GSTIN: 24ABCDE1234F1Z5. Location: Surat 395001. About: Sarees and dress material', $row->description);   // the GSTIN is stored upper-case
        $this->get('/join/vendor')->assertSee('Vendor request submitted!');
    }

    public function test_the_vendor_form_rules(): void
    {
        $this->vendor(['gstin' => '123'])->assertRedirect();
        $this->get('/join/vendor')->assertSee('Enter a valid 15-character GSTIN or leave blank');
        $this->vendor(['email' => 'nope'])->assertRedirect();
        $this->get('/join/vendor')->assertSee('Enter a valid email');
        $this->vendor(['business_type' => 'drone'])->assertRedirect();
        $this->assertSame([], $this->repo->vendors);

        $this->vendor(['gstin' => '', 'about' => ''])->assertRedirect();               // both are optional
        $this->assertCount(1, $this->repo->vendors);
    }

    public function test_a_logged_in_vendor_is_attached_to_their_account(): void
    {
        $me = $this->customer(5);
        $me->name = 'Asha Shah';

        $this->actingAs($me)->vendor()->assertRedirect();
        $this->assertSame(5, $this->repo->vendors[0]->user_id);
    }

    // ------------------------------------------------------------------ development request

    private function dev(array $extra = [])
    {
        return $this->post('/support/development', $extra + ['name' => 'Asha', 'phone' => '9123456780', 'email' => 'a@x.in', 'kind' => 'Create Application', 'budget' => 'Under ₹50,000', 'timeline' => 'Flexible', 'details' => 'A shop app with orders and a catalogue']);
    }

    public function test_a_development_request_maps_what_the_customer_needs(): void
    {
        $this->dev()->assertRedirect('/development-request');
        $this->dev(['kind' => 'Create Website'])->assertRedirect();
        $this->dev(['kind' => 'Both'])->assertRedirect();

        $this->assertSame(['app_development', 'web_development', 'app_development'], collect($this->repo->developments)->pluck('request_type')->all());
        $this->assertSame("A shop app with orders and a catalogue\nBudget: Under ₹50,000\nTimeline: Flexible", $this->repo->developments[0]->description);
        $this->assertStringStartsWith('[Needs both an app and a website] ', $this->repo->developments[2]->description);
    }

    public function test_the_development_form_rules(): void
    {
        $this->dev(['kind' => 'Teleport'])->assertRedirect();
        $this->dev(['email' => ''])->assertRedirect();
        $this->dev(['details' => 'short'])->assertRedirect();
        $this->assertSame([], $this->repo->developments);
        $this->get('/development-request')->assertSee('Please describe your requirement (at least 10 characters)');
    }

    // ------------------------------------------------------------------ help + callback

    public function test_the_help_page_shows_contacts_faqs_and_the_links_to_the_forms(): void
    {
        $this->get('/help')->assertOk()->assertSee('Help &amp; Support', false)->assertSee('Live chat on WhatsApp')->assertSee('wa.me/919999912345', false)->assertSee('care@takshallinone.com')->assertSee('How fast is Quick Delivery?')
            ->assertSee('Join as Delivery Partner')->assertSee('Log in so we know whom to call')->assertDontSee('id="help-root"', false);
    }

    public function test_a_customer_requests_a_callback_once_at_a_time(): void
    {
        $me = $this->customer(5);
        $me->name = 'Asha';

        $this->actingAs($me)->get('/help')->assertSee('name="_token"', false)->assertSee(route('support.callback.store'), false)->assertDontSee('Log in so we know whom to call');
        $this->actingAs($me)->post('/support/callback', ['name' => 'Someone else', 'mobile' => '1111111111'])->assertRedirect('/help');

        $this->assertSame([5, 'Asha', '9876543210'], [$this->repo->callbacks[0]->user_id, $this->repo->callbacks[0]->name, $this->repo->callbacks[0]->mobile]);
        $this->actingAs($me)->get('/help')->assertSee('Callback requested!')->assertSee('#1');

        $this->actingAs($me)->post('/support/callback')->assertRedirect('/help');
        $this->actingAs($me)->get('/help')->assertSee('You already have an open callback request');
        $this->assertCount(1, $this->repo->callbacks);
    }

    public function test_a_callback_needs_a_login(): void
    {
        $this->post('/support/callback')->assertRedirect('/login?next=%2Fsupport%2Fcallback');
        $this->assertSame([], $this->repo->callbacks);
    }

    // ------------------------------------------------------------------ demo mode untouched
}
