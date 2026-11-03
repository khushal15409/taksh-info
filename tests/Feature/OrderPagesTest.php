<?php

namespace Tests\Feature;

use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\WalletTransaction;
use App\Repositories\Contracts\BankDetailRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Support\Presenters\OrderTimeline;
use Illuminate\Http\UploadedFile;
use Tests\OrderTestCase;
use Tests\Support\FakeBankDetailRepository;
use Tests\Support\FakeBankOwnershipVerifier;
use Tests\Support\FakeWalletRepository;

require_once __DIR__.'/../Support/WalletFakes.php';

/** Phase 6 pages: the order list and order page (with cancel), the return flow, and the wallet - rendered by the server. */
class OrderPagesTest extends OrderTestCase
{
    private FakeWalletRepository $wallet;

    private FakeBankDetailRepository $banks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(WalletRepositoryInterface::class, $this->wallet = new FakeWalletRepository);
        $this->app->instance(BankDetailRepositoryInterface::class, $this->banks = new FakeBankDetailRepository);
        $this->app['validator']->setPresenceVerifier(new FakeBankOwnershipVerifier($this->banks));
    }

    private function me(int $id = 5)
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($this->customer($id));
    }

    /** An order of the customer straight in the store: one line of 2 x Rs 100, its stock reserved. */
    private function order(array $attrs = [], string $method = 'cod', string $payStatus = 'pending'): Order
    {
        $variant = $this->sold(1, 100, 10);
        $this->db->stock['7_1']->reserved_qty = 2;
        $o = $this->checkoutStore->createOrder($attrs + [
            'user_id' => 5, 'vendor_id' => 7, 'address_id' => 1, 'order_number' => 'ORD'.str_pad((string) $this->db->nextOrder, 8, 'X'), 'delivery_type' => 'normal',
            'order_status' => 'pending', 'order_type' => 'fm', 'items_total' => 150, 'taksh_charge_total' => 50, 'platform_fee' => 5, 'quick_delivery_fee' => 0, 'discount_total' => 0, 'grand_total' => 205,
            'created_at' => now()->subDay(),
        ]);
        $this->checkoutStore->createItem($o, ['product_variant_id' => $variant->id, 'qty' => 2, 'price' => 100, 'product_name' => 'Item 1', 'image_url' => null, 'return_days' => 7]);
        $this->db->items[$o->id][0]->setRelation('productVariant', $variant);
        $this->checkoutStore->createCharge($o, ['type' => 'platform', 'label' => 'Platform Fees', 'amount' => 5, 'is_discount' => false]);
        $this->checkoutStore->createPayment(['order_id' => $o->id, 'gateway' => $method === 'online' ? 'razorpay' : 'cod', 'amount' => 20500, 'currency' => 'INR', 'status' => $payStatus, 'method' => $method, 'raw_response' => []]);

        return $o;
    }

    // ------------------------------------------------------------------ unit: the timeline

    public function test_the_timeline_marks_how_far_an_order_has_got(): void
    {
        $at = fn (string $s) => array_column(OrderTimeline::build($s)['steps'], 'state', 'title');

        $this->assertSame(['Order Placed' => 'current', 'Confirmed' => 'todo', 'Packed' => 'todo', 'Shipped' => 'todo', 'Out for Delivery' => 'todo', 'Delivered' => 'todo'], $at('pending'));
        $this->assertSame('done', $at('shipped')['Packed']);
        $this->assertSame('current', $at('shipped')['Shipped']);
        $this->assertSame('todo', $at('shipped')['Delivered']);
        $this->assertSame('done', $at('delivered')['Delivered']);                         // the last step is complete, not "in progress"
        $this->assertSame(['Order Placed' => 'done', 'Cancelled' => 'bad'], $at('cancelled'));
        $this->assertSame(['Order Placed' => 'done', 'Mystery Status' => 'current'], $at('mystery_status'));
        $this->assertTrue(OrderTimeline::build('cancelled')['terminal']);
    }

    // ------------------------------------------------------------------ the order list (profile > My Orders)

    public function test_the_order_list_is_the_customers_own_and_newest_first(): void
    {
        $a = $this->order(['order_status' => 'delivered']);
        $b = $this->order(['order_status' => 'pending']);
        $this->order(['user_id' => 9]);                                       // someone else's

        $html = $this->me()->get('/profile?tab=orders')->assertOk()
            ->assertSee('My Orders')->assertSee('#'.$a->order_number)->assertSee('#'.$b->order_number)->assertSee('2 item(s)')->assertSee('₹205.00')
            ->assertSee('/tracking?id='.$b->id, false)->assertSee('Delivered')->assertSee('Pending')->assertDontSee('id="live-orders"', false)->getContent();

        $this->assertLessThan(strpos($html, '#'.$a->order_number), strpos($html, '#'.$b->order_number));   // newest first
        $this->assertStringNotContainsString('#ORD'.str_pad('3', 8, 'X'), $html);
    }

    public function test_the_order_list_filters_by_status_and_delivery_and_says_when_empty(): void
    {
        $d = $this->order(['order_status' => 'delivered']);
        $p = $this->order(['order_status' => 'pending']);
        $q = $this->order(['delivery_type' => '30_min', 'is_express' => true, 'order_status' => 'pending']);

        $this->me()->get('/profile?tab=orders&status=delivered')->assertSee('#'.$d->order_number)->assertDontSee('#'.$p->order_number)->assertSee('Clear');
        $this->me()->get('/profile?tab=orders&mode=quick')->assertSee('#'.$q->order_number)->assertSee('⚡ Quick')->assertDontSee('#'.$d->order_number);
        $this->me()->get('/profile?tab=orders&status=cancelled')->assertSee('No orders found')->assertSee('Try changing the filters.');
        $this->me()->get('/profile?tab=orders&status=bogus&mode=nope&from=garbage')->assertOk()->assertSee('#'.$d->order_number);   // junk is ignored
    }

    public function test_the_date_range_narrows_the_orders_shown(): void
    {
        $old = $this->order(['created_at' => now()->subDays(20)]);
        $new = $this->order(['created_at' => now()->subDay()]);
        $from = now()->subDays(5)->format('Y-m-d');

        $this->me()->get("/profile?tab=orders&from=$from")->assertSee('#'.$new->order_number)->assertDontSee('#'.$old->order_number);
        $this->me()->get('/profile?tab=orders&to='.now()->subDays(10)->format('Y-m-d'))->assertSee('#'.$old->order_number)->assertDontSee('#'.$new->order_number);
    }

    public function test_an_empty_order_list_offers_the_shop(): void
    {
        $this->me()->get('/profile?tab=orders')->assertSee('No orders found')->assertSee('Your placed orders will show up here.')->assertSee('Start Shopping');
    }

    // ------------------------------------------------------------------ the order page

    public function test_the_order_page_shows_status_address_bill_and_items(): void
    {
        $o = $this->order(['order_status' => 'confirmed'], 'online', 'paid');

        $this->me()->get('/tracking?id='.$o->id)->assertOk()
            ->assertSee('<title>Order #'.$o->order_number.' | TakshAllInOne</title>', false)->assertSee('Order Status')->assertSee('in progress')->assertSee('The shop accepted your order')
            ->assertSee('Asha')->assertSee('12 Ring Road')->assertSee('395001')->assertSee('Sold by:')->assertSee('Fresh Mart')
            ->assertSee('Platform Fees')->assertSee('Total')->assertSee('₹205.00')->assertSee('Item 1')->assertSee('Qty: 2 × ₹100.00', false)->assertSee('₹200.00')
            ->assertSee('Online')->assertSee('Paid')
            ->assertSee('Download Invoice')->assertSee('/orders/'.$o->order_number.'/invoice', false)
            ->assertSee('Need help with this order?')->assertSee('wa.me/919999912345', false)->assertSee('Cancel Order')->assertSee('id="cancel-order"', false)
            ->assertDontSee('Return Items')->assertDontSee('id="tracking-root"', false);
    }

    public function test_the_buttons_follow_the_order_status(): void
    {
        $shipped = $this->order(['order_status' => 'shipped']);
        $this->me()->get('/tracking?id='.$shipped->id)->assertDontSee('Cancel Order')->assertDontSee('Return Items')->assertSee('Shipped');

        $delivered = $this->order(['order_status' => 'delivered']);
        $this->me()->get('/tracking?id='.$delivered->id)->assertSee('Return Items')->assertSee('/return?id='.$delivered->id, false)->assertDontSee('Cancel Order');

        $cancelled = $this->order(['order_status' => 'cancelled']);
        $this->me()->get('/tracking?id='.$cancelled->id)->assertSee('Cancelled')->assertDontSee('Cancel Order')->assertDontSee('Return Items');

        $unpaid = $this->order([], 'online', 'initiated');
        $this->me()->get('/tracking?id='.$unpaid->id)->assertSee('Pay now')->assertSee('/order/pay?order_id='.$unpaid->id, false);
    }

    public function test_a_quick_order_on_its_way_gets_the_live_map(): void
    {
        $q = $this->order(['delivery_type' => '30_min', 'is_express' => true, 'order_status' => 'out_for_delivery']);

        $this->me()->get('/tracking?id='.$q->id)->assertSee('Live delivery location')->assertSee('id="order-map"', false)->assertSee('data-order="'.$q->id.'"', false)->assertSee('assets/js/modules/order.js', false);
        $standard = $this->order(['order_status' => 'out_for_delivery']);
        $this->me()->get('/tracking?id='.$standard->id)->assertDontSee('Live delivery location');
    }

    public function test_someone_elses_or_a_missing_order_is_not_shown(): void
    {
        $theirs = $this->order(['user_id' => 9]);

        $this->me()->get('/tracking?id='.$theirs->id)->assertNotFound()->assertSee('Order not found')->assertDontSee('#'.$theirs->order_number);
        $this->me()->get('/tracking?id=999')->assertNotFound();
        $this->me()->get('/tracking')->assertRedirect('/profile?tab=orders');

        $this->app['auth']->forgetGuards();
        $this->get('/tracking?id=1')->assertOk()->assertSee('Login to track your orders');
    }

    // ------------------------------------------------------------------ cancelling

    public function test_cancelling_an_order_releases_the_stock_and_comes_back_with_a_message(): void
    {
        $o = $this->order();

        $this->me()->post("/tracking/{$o->id}/cancel")->assertRedirect('/tracking?id='.$o->id);

        $this->assertSame('cancelled', $this->db->orders[$o->id]->order_status);
        $this->assertSame(0, $this->db->stock['7_1']->reserved_qty);              // the reserved pieces are free again
        $this->me()->get('/tracking?id='.$o->id)->assertSee('Order cancelled')->assertSee('Cancelled')->assertDontSee('Cancel Order</button>', false);
    }

    public function test_a_paid_order_is_refunded_when_cancelled_and_the_page_says_so(): void
    {
        config(['services.razorpay.refund_on_cancel' => true]);
        $o = $this->order([], 'online', 'paid');
        $this->db->payments[array_key_last($this->db->payments)]->razorpay_payment_id = 'pay_1';

        $this->me()->post("/tracking/{$o->id}/cancel")->assertRedirect();

        $this->assertCount(1, $this->razorpay->refunds);
        $this->me()->get('/tracking?id='.$o->id)->assertSee('Order cancelled. Your refund has been processed.');
    }

    public function test_an_order_that_cannot_be_cancelled_or_is_not_yours_is_refused_with_a_message(): void
    {
        $shipped = $this->order(['order_status' => 'shipped']);
        $this->me()->post("/tracking/{$shipped->id}/cancel")->assertRedirect('/tracking?id='.$shipped->id);
        $this->me()->get('/tracking?id='.$shipped->id)->assertSee('Order cannot be cancelled');
        $this->assertSame('shipped', $this->db->orders[$shipped->id]->order_status);

        $theirs = $this->order(['user_id' => 9]);
        $this->me()->post("/tracking/{$theirs->id}/cancel")->assertRedirect();
        $this->assertSame('pending', $this->db->orders[$theirs->id]->order_status);
        $this->assertSame(2, $this->db->stock['7_1']->reserved_qty);

        $done = $this->order();
        $this->me()->post("/tracking/{$done->id}/cancel");
        $this->me()->post("/tracking/{$done->id}/cancel")->assertRedirect('/tracking?id='.$done->id);   // pressing it twice cancels once
        $this->me()->get('/tracking?id='.$done->id)->assertSee('Order is already cancelled');
        $this->assertCount(1, collect($this->notifier->cancelled));

        $this->app['auth']->forgetGuards();
        $this->post("/tracking/{$done->id}/cancel")->assertRedirect('/login?next=%2Ftracking%2F'.$done->id.'%2Fcancel');
    }

    // ------------------------------------------------------------------ the return flow

    private function delivered(): array
    {
        $o = $this->order(['order_status' => 'delivered', 'delivered_at' => now()->subDays(2)]);

        return [$o, $this->db->items[$o->id][0]];
    }

    private function sendReturn(array $o, array $extra = [])
    {
        [$order, $item] = $o;

        return $this->me()->post('/return', $extra + ['order_id' => $order->id, 'order_item_id' => $item->id, 'quantity' => 2, 'reason' => 'The pack arrived damaged and leaking', 'resolution' => 'refund']);
    }

    public function test_the_return_form_is_offered_for_a_delivered_order(): void
    {
        [$o, $item] = $this->delivered();

        $this->me()->get('/return?id='.$o->id)->assertOk()
            ->assertSee('Return Items')->assertSee('Which item?')->assertSee('Item 1')->assertSee('name="order_item_id" value="'.$item->id.'"', false)->assertSee('data-qty="2"', false)
            ->assertSee('Wallet credit')->assertSee('Replacement')->assertSee('multipart/form-data', false)->assertSee('assets/js/modules/return.js', false)->assertDontSee('id="return-root"', false);
    }

    public function test_no_return_form_for_an_order_that_is_not_delivered_missing_or_a_guest(): void
    {
        $pending = $this->order();
        $this->me()->get('/return?id='.$pending->id)->assertOk()->assertSee('Not delivered yet')->assertDontSee('Which item?');
        $this->me()->get('/return?id=999')->assertNotFound()->assertSee('Order not found');
        $this->me()->get('/return')->assertNotFound();

        $theirs = $this->order(['user_id' => 9, 'order_status' => 'delivered']);
        $this->me()->get('/return?id='.$theirs->id)->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->get('/return?id=1')->assertOk()->assertSee('Login to return an item');
    }

    public function test_a_return_is_requested_with_photos_and_the_customer_lands_on_it(): void
    {
        $o = $this->delivered();

        $this->sendReturn($o, ['images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')]])->assertRedirect('/returns/1');

        $r = $this->returnStore->returns[1];
        $this->assertSame([5, ReturnStatus::PENDING], [$r->customer_id, $r->status]);
        $this->assertSame(2, $r->items->first()->quantity);
        $this->assertCount(2, $this->mediaStore->stored);                       // both photos were stored
        $this->me()->get('/returns/1')->assertSee('Return request submitted');
    }

    public function test_a_return_without_photos_works(): void
    {
        $o = $this->delivered();

        $this->sendReturn($o)->assertRedirect('/returns/1');
        $this->assertSame([], $this->mediaStore->stored);
    }

    public function test_a_photo_failure_does_not_lose_the_request(): void
    {
        $this->app->instance(\App\Services\Returns\ReturnMediaStore::class, new class implements \App\Services\Returns\ReturnMediaStore
        {
            public function put(UploadedFile $file): string
            {
                throw new \RuntimeException('disk full');
            }
        });
        $o = $this->delivered();

        $this->sendReturn($o, ['images' => [UploadedFile::fake()->image('a.jpg')]])->assertRedirect('/returns/1');
        $this->assertCount(1, $this->returnStore->returns);                     // the request stands
        $this->me()->get('/returns/1')->assertSee('the photos could not be uploaded');
    }

    public function test_a_bad_return_form_is_a_message_and_creates_nothing(): void
    {
        $o = $this->delivered();

        $this->me()->from('/return?id='.$o[0]->id)->post('/return', ['order_id' => $o[0]->id, 'order_item_id' => $o[1]->id, 'quantity' => 1, 'reason' => 'too short', 'resolution' => 'refund'])->assertRedirect('/return?id='.$o[0]->id);
        $this->me()->get('/return?id='.$o[0]->id)->assertSee('Please describe the problem (at least 10 characters)');

        $this->sendReturn($o, ['resolution' => 'cash'])->assertRedirect();
        $this->sendReturn($o, ['images' => array_fill(0, 6, UploadedFile::fake()->image('a.jpg'))])->assertRedirect();
        $this->sendReturn($o, ['images' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])->assertRedirect();
        $this->assertSame([], $this->returnStore->returns);
    }

    public function test_the_return_rules_of_the_service_still_apply(): void
    {
        $o = $this->delivered();

        $this->sendReturn($o, ['quantity' => 5])->assertRedirect('/return?id='.$o[0]->id);
        $this->me()->get('/return?id='.$o[0]->id)->assertSee('You cannot return more pieces than are left on this item');

        $this->sendReturn($o)->assertRedirect('/returns/1');
        $this->sendReturn($o, ['quantity' => 1])->assertRedirect('/return?id='.$o[0]->id);          // an open request already exists: no second one
        $this->assertCount(1, $this->returnStore->returns);

        $theirs = $this->order(['user_id' => 9, 'order_status' => 'delivered', 'delivered_at' => now()]);
        $this->sendReturn([$theirs, $this->db->items[$theirs->id][0]])->assertRedirect();
        $this->assertCount(1, $this->returnStore->returns);
    }

    public function test_the_returns_tab_and_a_return_page_belong_to_the_customer(): void
    {
        $o = $this->delivered();
        $this->sendReturn($o)->assertRedirect('/returns/1');

        $this->me()->get('/profile?tab=returns')->assertOk()->assertSee('My Returns')->assertSee('Item 1 × 2')->assertSee('Pending')->assertSee('/returns/1', false)->assertDontSee('id="live-returns"', false);
        $this->me()->get('/returns/1')->assertOk()->assertSee('Item 1 × 2')->assertSee('The pack arrived damaged and leaking')->assertSee('Return request created by customer')->assertSee('My Returns');

        $this->me()->get('/returns/99')->assertNotFound()->assertSee('Return not found');
        $this->me(9)->get('/returns/1')->assertNotFound()->assertDontSee('damaged');
        $this->me(9)->get('/profile?tab=returns')->assertSee('No returns yet');
    }

    // ------------------------------------------------------------------ the wallet

    private function bank(int $userId = 5): \App\Models\BankDetail
    {
        return $this->banks->create(['user_id' => $userId, 'account_holder_name' => 'Asha Shah', 'bank_name' => 'SBI', 'account_number' => '123456789012', 'ifsc_code' => 'SBIN0001234']);
    }

    private function tx(array $attrs): WalletTransaction
    {
        return (new WalletTransaction)->forceFill($attrs + ['id' => count($this->wallet->ledger) + 1, 'type' => 'credit', 'amount' => 10, 'status' => 'completed', 'created_at' => now()])
            ->setRelation('order', null)->setRelation('refund', null)->setRelation('walletWithdrawalRequest', null);
    }

    public function test_a_guest_is_asked_to_log_in_to_open_the_wallet(): void
    {
        $this->get('/wallet')->assertOk()->assertSee('Login to open your wallet')->assertSee('/login?next=%2Fwallet', false);
    }

    public function test_the_wallet_shows_the_balance_banks_and_history(): void
    {
        config(['wallet.rupees_per_point' => 0.5]);
        $this->wallet->balances[5] = 250;
        $this->bank();
        $this->wallet->ledger = collect([
            $this->tx(['source' => 'order_reward', 'description' => 'Reward for order ORD1', 'amount' => 12]),
            $this->tx(['type' => 'debit', 'source' => 'withdrawal', 'amount' => 100, 'status' => 'pending']),
        ]);

        $this->me()->get('/wallet')->assertOk()
            ->assertSee('My Wallet')->assertSee('Available to withdraw')->assertSee('250')->assertSee('≈ ₹125.00')->assertSee('1 point = ₹0.5')->assertSee('Minimum withdrawal 100 points')
            ->assertSee('Withdraw to bank')->assertSee('SBI 12********12')->assertSee('Asha Shah')->assertSee('SBIN0001234')->assertDontSee('123456789012')    // the number is masked
            ->assertSee('Transaction history')->assertSee('Reward for order ORD1')->assertSee('+12')->assertSee('−100')->assertSee('Pending')
            ->assertSee('assets/js/modules/wallet.js', false)->assertDontSee('id="wallet-root"', false);
    }

    public function test_withdrawing_is_blocked_until_there_is_a_bank_and_enough_points(): void
    {
        $this->wallet->balances[5] = 250;
        $this->me()->get('/wallet')->assertSee('No bank account added')->assertSee('disabled', false);

        $this->bank();
        $this->wallet->balances[5] = 50;
        $this->me()->get('/wallet')->assertSee('disabled', false);
    }

    public function test_adding_a_bank_account_stores_it_masked_and_returns_to_the_wallet(): void
    {
        $this->me()->post('/wallet/bank-accounts', [
            'account_holder_name' => 'Asha Shah', 'bank_name' => 'SBI', 'account_number' => '1234 5678 9012', 'ifsc_code' => 'sbin0001234', 'branch' => 'Surat', 'upi' => 'asha@sbi',
        ])->assertRedirect('/wallet');

        $this->assertSame('123456789012', $this->banks->rows[1]->account_number);       // stored whole, spaces removed
        $this->assertSame('SBIN0001234', $this->banks->rows[1]->ifsc_code);
        $this->me()->get('/wallet')->assertSee('Bank account added')->assertSee('12********12');
    }

    public function test_a_bad_bank_form_is_a_message(): void
    {
        $this->me()->from('/wallet')->post('/wallet/bank-accounts', ['account_holder_name' => 'A', 'bank_name' => 'B', 'account_number' => '1', 'ifsc_code' => 'ABCD1234567'])->assertRedirect('/wallet');
        $this->me()->get('/wallet')->assertSee('The IFSC code must be valid');
        $this->me()->from('/wallet')->post('/wallet/bank-accounts', [])->assertRedirect('/wallet');
        $this->assertSame([], $this->banks->rows);
    }

    public function test_a_withdrawal_is_requested_from_available_points_to_an_own_account(): void
    {
        $this->wallet->balances[5] = 250;
        $bank = $this->bank();

        $this->me()->post('/wallet/withdrawals', ['points' => 120, 'bank_detail_id' => $bank->id])->assertRedirect('/wallet');

        $this->assertCount(1, $this->wallet->withdrawals);
        $this->assertSame(1, $this->wallet->locked);                                    // taken under a lock on the wallet
        $this->me()->get('/wallet')->assertSee('Withdrawal request submitted')->assertSee('120 pts')->assertSee('Pending');

        // 250 - 120 pending = 130 left: asking for more is refused, and so is asking twice for the same points
        $this->me()->post('/wallet/withdrawals', ['points' => 131, 'bank_detail_id' => $bank->id])->assertRedirect('/wallet');
        $this->me()->get('/wallet')->assertSee('Insufficient available points for this withdrawal');
        $this->assertCount(1, $this->wallet->withdrawals);
    }

    public function test_withdrawal_rules_minimum_and_own_account(): void
    {
        $this->wallet->balances[5] = 500;
        $mine = $this->bank(5);
        $theirs = $this->bank(9);

        $this->me()->from('/wallet')->post('/wallet/withdrawals', ['points' => 99, 'bank_detail_id' => $mine->id])->assertRedirect('/wallet');
        $this->me()->get('/wallet')->assertSee('Minimum 100 points required for withdrawal');
        $this->me()->from('/wallet')->post('/wallet/withdrawals', ['points' => 100, 'bank_detail_id' => $theirs->id])->assertRedirect('/wallet');   // someone else's account
        $this->me()->from('/wallet')->post('/wallet/withdrawals', ['points' => 100])->assertRedirect('/wallet');
        $this->assertSame([], $this->wallet->withdrawals);
    }

    public function test_the_wallet_actions_need_a_login(): void
    {
        $this->post('/wallet/bank-accounts', [])->assertRedirect();
        $this->post('/wallet/withdrawals', [])->assertRedirect();
        $this->assertSame([], $this->banks->rows);
    }

    // ------------------------------------------------------------------ demo mode untouched
}
