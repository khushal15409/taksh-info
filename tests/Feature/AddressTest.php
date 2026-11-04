<?php

namespace Tests\Feature;

use App\Models\Pincode;
use Tests\AccountTestCase;
use Tests\Support\FakeLocationRepository;

class AddressTest extends AccountTestCase
{
    private function payload(array $extra = []): array
    {
        return $extra + ['name' => 'Asha Shah', 'mobile' => '9876543210', 'address_line_1' => '12 Ring Road', 'pincode' => '395001', 'address_type' => 'home'];
    }

    private function user()
    {
        return $this->customer(5);
    }

    private function knownPincode(): void
    {
        $this->locations->areas['395001'] = FakeLocationRepository::area(300, 200, 100, '395001');
    }

    public function test_addresses_need_a_login(): void
    {
        $this->getJson('/address/addresses')->assertStatus(401);
        $this->postJson('/address/add', $this->payload())->assertStatus(401);
        $this->putJson('/address/1', [])->assertStatus(401);
    }

    public function test_the_first_address_becomes_the_default_and_the_list_separates_it(): void
    {
        $this->knownPincode();

        $this->actingAs($this->user())->postJson('/address/add', $this->payload(['address_type' => 'office', 'landmark' => 'Near temple']))->assertStatus(201)
            ->assertJsonPath('status', true)->assertJsonPath('message', 'Address added successfully')->assertJsonPath('data.address_id', 1);
        $this->actingAs($this->user())->postJson('/address/user/addresses', $this->payload(['name' => 'Second']))->assertStatus(201);

        $first = $this->addresses->items[1];
        $this->assertTrue($first->is_default);
        $this->assertSame('work', $first->type);                          // "office" is stored as "work"
        $this->assertSame([100, 200, 300], [$first->state_id, $first->city_id, $first->area_id]);
        $this->assertFalse($this->addresses->items[2]->is_default);

        $this->actingAs($this->user())->getJson('/address/addresses')->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.default_address.address_type', 'office')    // and shown as "office"
            ->assertJsonPath('data.default_address.city', 'Surat')
            ->assertJsonPath('data.default_address.landmark', 'Near temple')
            ->assertJsonCount(1, 'data.addresses')->assertJsonPath('data.addresses.0.name', 'Second');
    }

    public function test_asking_for_the_default_clears_the_others(): void
    {
        $this->knownPincode();
        $this->actingAs($this->user())->postJson('/address/add', $this->payload());
        $this->actingAs($this->user())->postJson('/address/add', $this->payload(['is_default' => true]))->assertStatus(201);

        $this->assertFalse($this->addresses->items[1]->is_default);
        $this->assertTrue($this->addresses->items[2]->is_default);
    }

    public function test_input_is_validated_and_the_mobile_is_normalised(): void
    {
        $this->knownPincode();

        $this->actingAs($this->user())->postJson('/address/add', ['pincode' => '395001'])->assertStatus(422)
            ->assertJsonStructure(['data' => ['errors' => ['name', 'mobile', 'address_line_1', 'address_type']]]);
        $this->actingAs($this->user())->postJson('/address/add', $this->payload(['address_type' => 'cabin']))->assertStatus(422);
        $this->actingAs($this->user())->postJson('/address/add', $this->payload(['mobile' => '+91 98765 43210']))->assertStatus(201);
        $this->assertSame('9876543210', $this->addresses->items[1]->mobile);
    }

    public function test_state_city_and_area_are_worked_out_from_the_postal_directory(): void
    {
        $this->locations->directory['380015'] = (new Pincode)->forceFill(['pincode' => '380015', 'statename' => 'GUJARAT', 'district' => 'AHMEDABAD', 'officename' => 'Satellite S.O']);

        $this->actingAs($this->user())->postJson('/address/add', $this->payload(['pincode' => '380015']))->assertStatus(201);

        $this->assertSame([['Gujarat', 'Ahmedabad', 'Satellite S.O', '380015']], $this->locations->created);
    }

    public function test_an_unknown_pincode_is_registered_inactive_only_with_state_and_city_names(): void
    {
        $this->actingAs($this->user())->postJson('/address/add', $this->payload(['pincode' => '395999']))->assertStatus(422)
            ->assertJsonPath('status', false)->assertJsonPath('message', 'We could not find this pincode in our service areas. Please check the pincode.');

        $this->actingAs($this->user())->postJson('/address/add', $this->payload(['pincode' => '12345', 'state_name' => 'Gujarat', 'city_name' => 'Surat']))->assertStatus(422);

        $this->actingAs($this->user())->postJson('/address/add', $this->payload(['pincode' => '395999', 'state_name' => 'Gujarat', 'city_name' => 'Surat', 'area_name' => 'Vesu']))->assertStatus(201);
        $this->assertSame(0, $this->locations->registered[0]['status']);          // never serviceable on its own
        $this->assertSame('Vesu', $this->locations->registered[0]['officename']);
    }

    public function test_update_changes_only_what_is_sent_and_moves_a_changed_pincode(): void
    {
        $this->knownPincode();
        $this->locations->areas['380001'] = FakeLocationRepository::area(310, 210, 100, '380001');
        $this->addresses->seed(5, ['is_default' => true, 'state_id' => 100, 'city_id' => 200, 'area_id' => 300]);

        $this->actingAs($this->user())->putJson('/address/1', ['landmark' => 'Behind mall'])->assertOk()
            ->assertJsonPath('message', 'Address updated successfully')->assertJsonPath('data.landmark', 'Behind mall')->assertJsonPath('data.name', 'Asha');
        $this->assertSame(300, $this->addresses->items[1]->area_id);

        $this->actingAs($this->user())->putJson('/address/1', ['pincode' => '380001', 'address_type' => 'other'])->assertOk();
        $this->assertSame([310, 'other'], [$this->addresses->items[1]->area_id, $this->addresses->items[1]->type]);

        $this->actingAs($this->user())->putJson('/address/1', ['pincode' => '111111'])->assertStatus(422);
        $this->actingAs($this->user())->putJson('/address/1', ['mobile' => '123'])->assertStatus(422);
    }

    public function test_an_address_can_be_made_the_default_through_update(): void
    {
        $this->addresses->seed(5, ['is_default' => true]);
        $this->addresses->seed(5);

        $this->actingAs($this->user())->putJson('/address/2', ['is_default' => true])->assertOk();

        $this->assertFalse($this->addresses->items[1]->is_default);
        $this->assertTrue($this->addresses->items[2]->is_default);
    }

    public function test_someone_elses_address_cannot_be_touched(): void
    {
        $this->addresses->seed(9, ['is_default' => true]);

        $this->actingAs($this->user())->putJson('/address/1', ['name' => 'Hacked'])->assertStatus(404)
            ->assertJsonPath('status', false)->assertJsonPath('message', 'Address not found or does not belong to you');
        $this->actingAs($this->user())->postJson('/address/user/addresses/set-default', ['address_id' => 1])->assertStatus(404);
        $this->actingAs($this->user())->postJson('/address/user/addresses/delete', ['address_id' => 1])->assertStatus(404);
        $this->assertSame('Asha', $this->addresses->items[1]->name);
    }

    public function test_set_default(): void
    {
        $this->addresses->seed(5, ['is_default' => true]);
        $this->addresses->seed(5);

        $this->actingAs($this->user())->postJson('/address/user/addresses/set-default', ['address_id' => 2])->assertOk()
            ->assertJsonPath('message', 'Default address updated successfully')
            ->assertCookieExpired(\App\Services\Quick\QuickLocation::COOKIE);   // a pincode typed earlier no longer overrides the chosen address

        $this->assertSame([false, true], [$this->addresses->items[1]->is_default, $this->addresses->items[2]->is_default]);
        $this->actingAs($this->user())->postJson('/address/user/addresses/set-default', [])->assertStatus(422);
    }

    public function test_deleting(): void
    {
        $this->addresses->seed(5, ['is_default' => true]);
        $this->actingAs($this->user())->postJson('/address/user/addresses/delete', ['address_id' => 1])->assertStatus(400)
            ->assertJsonPath('message', 'Cannot delete the only address');

        $this->addresses->seed(5);
        $this->addresses->seed(5);
        $this->actingAs($this->user())->postJson('/address/user/addresses/delete', ['address_id' => 1])->assertOk()->assertJsonPath('message', 'Address deleted successfully');

        $this->assertArrayNotHasKey(1, $this->addresses->items);
        $this->assertTrue($this->addresses->items[3]->is_default);            // the newest remaining address takes over
    }

    // ------------------------------------------------------------------ map lookups (F3)

    public function test_place_search_geocode_and_reverse(): void
    {
        $place = ['display_name' => 'Athwa, Surat', 'latitude' => 21.17, 'longitude' => 72.83, 'address' => ['line' => 'Ring Road', 'area' => 'Athwa', 'city' => 'Surat', 'state' => 'Gujarat', 'pincode' => '395001']];
        $this->places->places = [$place, $place];
        $this->places->reverse = $place;

        $this->getJson('/location/search?q=athwa')->assertOk()->assertJsonCount(2, 'data.places')->assertJsonPath('data.places.0.address.pincode', '395001');
        $this->getJson('/location/geocode?q=athwa')->assertOk()->assertJsonPath('data.place.latitude', 21.17);
        $this->getJson('/location/reverse-geocode?latitude=21.17&longitude=72.83')->assertOk()->assertJsonPath('data.place.address.city', 'Surat');

        $this->places->places = [];
        $this->places->reverse = null;
        $this->getJson('/location/geocode?q=nowhere')->assertStatus(404);
        $this->getJson('/location/reverse-geocode?latitude=1&longitude=2')->assertStatus(404);
        $this->getJson('/location/search?q=ab')->assertStatus(422);
        $this->getJson('/location/reverse-geocode?latitude=999&longitude=2')->assertStatus(422);
    }
}
