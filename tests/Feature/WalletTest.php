<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Refund;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawalRequest;
use App\Repositories\Contracts\BankDetailRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use Tests\CatalogTestCase;
use Tests\Support\FakeBankDetailRepository;
use Tests\Support\FakeBankOwnershipVerifier;
use Tests\Support\FakeWalletRepository;

require_once __DIR__.'/../Support/WalletFakes.php';

class WalletTest extends CatalogTestCase
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

    private function bank(int $userId = 5, array $attrs = []): \App\Models\BankDetail
    {
        return $this->banks->create($attrs + ['user_id' => $userId, 'account_holder_name' => 'Asha Shah', 'bank_name' => 'SBI', 'account_number' => '123456789012', 'ifsc_code' => 'SBIN0001234']);
    }

    private function tx(array $attrs): WalletTransaction
    {
        return (new WalletTransaction)->forceFill($attrs + ['id' => count($this->wallet->ledger) + 1, 'type' => 'credit', 'amount' => 10, 'status' => 'completed', 'created_at' => now()])
            ->setRelation('order', null)->setRelation('refund', null)->setRelation('walletWithdrawalRequest', null);
    }

    public function test_everything_needs_a_login(): void
    {
        foreach (['/wallet/summary', '/wallet/transactions', '/wallet/withdrawal-requests', '/bank-detail', '/return/refund/wallet-transactions'] as $path) {
            $this->getJson($path)->assertStatus(401);
        }
        $this->postJson('/wallet/withdrawal-requests', [])->assertStatus(401);
        $this->postJson('/bank-detail', [])->assertStatus(401);
    }

    // ------------------------------------------------------------------ summary (I1)

    public function test_the_summary_separates_what_is_promised_to_pending_withdrawals(): void
    {
        $this->wallet->balances[5] = 250;
        $this->wallet->withdrawals[] = (new WalletWithdrawalRequest)->forceFill(['id' => 1, 'user_id' => 5, 'points' => 100, 'status' => 'pending']);
        $this->wallet->withdrawals[] = (new WalletWithdrawalRequest)->forceFill(['id' => 2, 'user_id' => 5, 'points' => 40, 'status' => 'paid']);   // already paid out: no longer pending
        config(['wallet.rupees_per_point' => 0.5]);

        $this->actingAs($this->customer(5))->getJson('/wallet/summary')->assertOk()
            ->assertJsonPath('message', 'Wallet summary fetched successfully')
            ->assertJsonPath('data.balance_points', 250)->assertJsonPath('data.balance_rupees_equivalent', 125)
            ->assertJsonPath('data.pending_withdrawal_points', 100)->assertJsonPath('data.available_points', 150)->assertJsonPath('data.available_rupees_equivalent', 75)
            ->assertJsonPath('data.min_withdrawal_points', 100)->assertJsonPath('data.rupees_per_point', 0.5)
            ->assertJsonPath('data.order_reward.points_formula', 'floor(order_total_rupees / 100)')->assertJsonPath('data.order_reward.example', '₹1000 spent → 10 point(s)');
    }

    public function test_available_points_never_go_below_zero(): void
    {
        $this->wallet->balances[5] = 50;
        $this->wallet->withdrawals[] = (new WalletWithdrawalRequest)->forceFill(['id' => 1, 'user_id' => 5, 'points' => 80, 'status' => 'pending']);

        $this->actingAs($this->customer(5))->getJson('/wallet/summary')->assertJsonPath('data.available_points', 0);
    }

    // ------------------------------------------------------------------ ledger (I2) and refunds into the wallet (I5)

    public function test_the_ledger_lists_credits_and_debits_with_their_source(): void
    {
        $order = (new Order)->forceFill(['order_number' => 'ORD123', 'order_status' => 'delivered']);
        $this->wallet->ledger = collect([
            $this->tx(['source' => 'order_reward', 'description' => 'Reward for order ORD123', 'order_id' => 9])->setRelation('order', $order),
            $this->tx(['type' => 'debit', 'amount' => 100, 'source' => 'withdrawal', 'wallet_withdrawal_request_id' => 3])
                ->setRelation('walletWithdrawalRequest', (new WalletWithdrawalRequest)->forceFill(['id' => 3, 'status' => 'approved', 'points' => 100])),
        ]);

        $res = $this->actingAs($this->customer(5))->getJson('/wallet/transactions?per_page=20')->assertOk()
            ->assertJsonPath('message', 'Wallet transactions retrieved successfully')
            ->assertJsonPath('data.pagination.per_page', 20)->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.items.0.order.order_number', 'ORD123')
            ->assertJsonPath('data.items.1.type', 'debit')->assertJsonPath('data.items.1.withdrawal_request.status', 'approved');

        $this->assertSame(20, $this->wallet->lastPerPage);
        $this->actingAs($this->customer(5))->getJson('/wallet/transactions?per_page=500')->assertStatus(422);
    }

    public function test_refunds_that_came_into_the_wallet(): void
    {
        $refund = (new Refund)->forceFill(['id' => 4, 'return_id' => 7, 'status' => 'completed', 'amount' => 199.5, 'payment_method' => 'wallet'])
            ->setRelation('return', (new \App\Models\ProductReturn)->forceFill(['id' => 7, 'status' => 'completed']));
        $this->wallet->ledger = collect([$this->tx(['source' => 'refund', 'refund_id' => 4, 'amount' => 199.5])->setRelation('refund', $refund), $this->tx(['source' => 'order_reward'])]);

        $this->actingAs($this->customer(5))->getJson('/return/refund/wallet-transactions')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.refund.return_id', 7)->assertJsonPath('data.data.0.refund.return_status', 'completed')->assertJsonPath('data.data.0.amount', 199.5);
    }

    // ------------------------------------------------------------------ bank accounts (I3)

    public function test_bank_accounts_are_added_and_listed_with_the_number_masked(): void
    {
        $this->actingAs($this->customer(5))->getJson('/bank-detail')->assertOk()->assertJsonPath('message', 'No bank details found')->assertJsonPath('data', []);

        $res = $this->actingAs($this->customer(5))->postJson('/bank-detail', [
            'account_holder_name' => 'Asha Shah', 'bank_name' => 'SBI', 'account_number' => '1234 5678 9012', 'ifsc_code' => 'sbin0001234', 'branch' => 'Surat', 'upi' => 'asha@sbi',
        ])->assertStatus(201)->assertJsonPath('status', true)->assertJsonPath('message', 'Bank details saved successfully')
            ->assertJsonPath('data.account_number', '12********12')->assertJsonPath('data.ifsc_code', 'SBIN0001234');

        $this->assertArrayNotHasKey('account_number_full', $res->json('data'));            // the full number is never sent back to the browser
        $this->assertSame('123456789012', $this->banks->rows[1]->account_number);          // but it is stored whole, spaces removed

        $this->bank(9);                                                                      // someone else's account
        $this->actingAs($this->customer(5))->getJson('/bank-detail')->assertJsonPath('message', 'Bank details fetched successfully')->assertJsonCount(1, 'data');
    }

    public function test_bank_account_validation(): void
    {
        $this->actingAs($this->customer(5))->postJson('/bank-detail', ['ifsc_code' => 'BAD'])->assertStatus(422)
            ->assertJsonStructure(['data' => ['errors' => ['account_holder_name', 'bank_name', 'account_number', 'ifsc_code']]]);
        $this->actingAs($this->customer(5))->postJson('/bank-detail', ['account_holder_name' => 'A', 'bank_name' => 'B', 'account_number' => '1', 'ifsc_code' => 'ABCD1234567'])
            ->assertStatus(422)->assertJsonPath('data.errors.ifsc_code.0', 'The IFSC code must be valid (e.g. SBIN0001234).');
    }

    // ------------------------------------------------------------------ withdrawals (I4)

    public function test_a_withdrawal_is_requested_against_available_points_and_an_own_account(): void
    {
        $this->wallet->balances[5] = 250;
        $bank = $this->bank(5);
        $post = fn (array $body) => $this->actingAs($this->customer(5))->postJson('/wallet/withdrawal-requests', $body);

        $post(['points' => 120, 'bank_detail_id' => $bank->id])->assertStatus(201)
            ->assertJsonPath('message', 'Withdrawal request submitted successfully')
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.points', 120)->assertJsonPath('data.amount_rupees', 120)->assertJsonPath('data.bank_detail_id', $bank->id);

        $this->assertSame(1, $this->wallet->locked);                                       // taken under a lock on the wallet
        $post(['points' => 131, 'bank_detail_id' => $bank->id])->assertStatus(422)->assertJsonPath('message', 'Insufficient available points for this withdrawal');   // 250 - 120 pending = 130
        $post(['points' => 130, 'bank_detail_id' => $bank->id])->assertStatus(201);
        $this->assertCount(2, $this->wallet->withdrawals);
    }

    public function test_withdrawal_rules(): void
    {
        $this->wallet->balances[5] = 500;
        $mine = $this->bank(5);
        $theirs = $this->bank(9);
        $post = fn (array $body) => $this->actingAs($this->customer(5))->postJson('/wallet/withdrawal-requests', $body);

        $post(['points' => 99, 'bank_detail_id' => $mine->id])->assertStatus(422)->assertJsonStructure(['data' => ['errors' => ['points']]]);   // below the minimum
        $post(['points' => 100, 'bank_detail_id' => $theirs->id])->assertStatus(422)->assertJsonStructure(['data' => ['errors' => ['bank_detail_id']]]);   // not the customer's account
        $post(['points' => 100])->assertStatus(422);
        $this->assertSame([], $this->wallet->withdrawals);

        config(['wallet.min_withdrawal_points' => 50, 'wallet.rupees_per_point' => 2]);
        $post(['points' => 50, 'bank_detail_id' => $mine->id])->assertStatus(201)->assertJsonPath('data.amount_rupees', 100);                      // 1 point = Rs 2
    }

    public function test_the_withdrawal_list_belongs_to_the_customer(): void
    {
        $this->wallet->withdrawals[] = (new WalletWithdrawalRequest)->forceFill(['id' => 1, 'user_id' => 5, 'points' => 100, 'amount_rupees' => 100, 'status' => 'rejected', 'rejection_reason' => 'Bank details mismatch', 'created_at' => now()])->setRelation('bankDetail', $this->bank(5));
        $this->wallet->withdrawals[] = (new WalletWithdrawalRequest)->forceFill(['id' => 2, 'user_id' => 9, 'points' => 100, 'amount_rupees' => 100, 'status' => 'pending', 'created_at' => now()])->setRelation('bankDetail', null);

        $this->actingAs($this->customer(5))->getJson('/wallet/withdrawal-requests')->assertOk()
            ->assertJsonPath('data.pagination.total', 1)->assertJsonPath('data.items.0.status', 'rejected')->assertJsonPath('data.items.0.rejection_reason', 'Bank details mismatch')
            ->assertJsonPath('data.items.0.bank_detail.account_number', '12********12');
    }
}
