<?php

namespace Tests\Feature;

use App\Enums\ReturnStatus;
use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Tests\OrderTestCase;

class ReturnsTest extends OrderTestCase
{
    /** A delivered order of the customer with one line of 3 pieces (return window 7 days). */
    private function delivered(array $attrs = [], array $line = []): array
    {
        $variant = $this->sold(1, 100, 10);
        $o = $this->checkoutStore->createOrder($attrs + [
            'user_id' => 5, 'vendor_id' => 7, 'address_id' => 1, 'order_number' => 'ORDDELIV01', 'delivery_type' => 'normal', 'order_status' => 'delivered',
            'delivered_at' => now()->subDays(2), 'grand_total' => 300,
        ]);
        $this->checkoutStore->createItem($o, $line + ['product_variant_id' => 1, 'qty' => 3, 'price' => 100, 'product_name' => 'Item 1', 'return_days' => 7]);
        $item = $this->db->items[$o->id][0];
        $item->setRelation('productVariant', $variant);

        return [$o, $item];
    }

    private function request(Order $o, $item, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->customer(5))->postJson('/return/customer/create', $extra + [
            'order_id' => $o->id, 'order_item_id' => $item->id, 'quantity' => 2, 'reason' => 'The pack arrived damaged and leaking', 'resolution' => 'refund',
        ]);
    }

    public function test_a_delivered_line_can_be_returned_in_part(): void
    {
        [$o, $item] = $this->delivered();

        $this->request($o, $item)->assertOk()
            ->assertJsonPath('message', 'Return requested successfully')
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.status_label', 'Pending')
            ->assertJsonPath('data.resolution', 'refund')->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.status_history.0.notes', 'Return request created by customer');

        $r = $this->returnStore->returns[1];
        $this->assertSame([5, 7, ReturnStatus::PENDING], [$r->customer_id, $r->vendor_id, $r->status]);
        $this->assertSame(2, $r->items->first()->quantity);                      // what is coming back is recorded
    }

    public function test_the_rules_for_a_return(): void
    {
        [$o, $item] = $this->delivered();

        $this->request($o, $item, ['quantity' => 4])->assertStatus(400)->assertJsonPath('message', 'You cannot return more pieces than are left on this item');
        $this->request($o, $item, ['reason' => 'too short'])->assertStatus(422)->assertJsonStructure(['data' => ['errors' => ['reason']]]);
        $this->request($o, $item, ['resolution' => 'cash'])->assertStatus(422);

        $this->request($o, $item)->assertOk();
        $this->request($o, $item, ['quantity' => 1])->assertStatus(400)->assertJsonPath('message', 'Return request already exists for this order item');

        $this->returnStore->returns[1]->status = ReturnStatus::REJECTED;       // a rejected request frees its pieces again
        $this->returnStore->returned[$item->id] = 0;
        $this->request($o, $item, ['quantity' => 3])->assertOk();
    }

    public function test_only_a_delivered_order_inside_its_return_window(): void
    {
        [$o, $item] = $this->delivered(['order_status' => 'shipped']);
        $this->request($o, $item)->assertStatus(400)->assertJsonPath('message', 'Order has not been delivered yet');

        $o->order_status = 'delivered';
        $o->delivered_at = now()->subDays(8);                                   // the window is 7 days
        $this->request($o, $item)->assertStatus(400)->assertJsonPath('message', 'The return window for this item has closed');

        $this->actingAs($this->customer(9))->postJson('/return/customer/create', ['order_id' => $o->id, 'order_item_id' => $item->id, 'quantity' => 1, 'reason' => 'It was not what I ordered', 'resolution' => 'refund'])->assertStatus(404);
    }

    public function test_pictures_are_attached_to_the_customers_own_return(): void
    {
        [$o, $item] = $this->delivered();
        $this->request($o, $item);

        $res = $this->actingAs($this->customer(5))->post('/return/customer/upload-media', [
            'return_id' => 1, 'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('message', 'Media uploaded successfully')->assertJsonCount(2, 'data');

        $this->assertCount(2, $this->mediaStore->stored);
        $this->assertStringContainsString('returns/031126/return_0.jpg', $res->json('data.0.file_url'));

        $this->actingAs($this->customer(9))->post('/return/customer/upload-media', ['return_id' => 1, 'images' => [UploadedFile::fake()->image('a.jpg')]], ['Accept' => 'application/json'])->assertStatus(404);
        $this->actingAs($this->customer(5))->post('/return/customer/upload-media', ['return_id' => 1, 'images' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])->assertStatus(422);
        $this->actingAs($this->customer(5))->post('/return/customer/upload-media', ['return_id' => 1, 'images' => array_fill(0, 6, UploadedFile::fake()->image('a.jpg'))], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_list_detail_and_tracking_belong_to_the_customer(): void
    {
        [$o, $item] = $this->delivered();
        $this->request($o, $item);
        $this->returnStore->returns[1]->status = ReturnStatus::PICKUP_SCHEDULED;
        $this->returnStore->pickup = (object) ['tracking_id' => 'TRK1', 'status' => 'pickup_assigned', 'origin_center' => 'Surat'];

        $this->actingAs($this->customer(5))->getJson('/return/customer/list')->assertOk()->assertJsonPath('message', 'Returns retrieved successfully')->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.status', 'pickup_scheduled');
        $this->actingAs($this->customer(5))->getJson('/return/customer/list?status=completed')->assertJsonPath('data.total', 0);
        $this->actingAs($this->customer(5))->getJson('/return/customer/list?status=nonsense')->assertStatus(422);
        $this->actingAs($this->customer(5))->getJson('/return/customer/1')->assertOk()->assertJsonPath('data.reason', 'The pack arrived damaged and leaking');

        $this->actingAs($this->customer(5))->getJson('/return/customer/1/track')->assertOk()
            ->assertJsonPath('data.current_status', 'pickup_scheduled')->assertJsonPath('data.current_status_label', 'Pickup Scheduled')->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.pickup.tracking_id', 'TRK1')->assertJsonPath('data.pickup.status_label', 'Pickup Assigned')->assertJsonPath('data.status_history.0.status', 'pending');

        $this->actingAs($this->customer(9))->getJson('/return/customer/1')->assertStatus(404)->assertJsonPath('message', 'Return request not found');
        $this->actingAs($this->customer(9))->getJson('/return/customer/1/track')->assertStatus(404);
    }
}
