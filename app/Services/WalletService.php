<?php

namespace App\Services;

use App\Exceptions\CartProblemException;
use App\Http\Resources\Wallet\WithdrawalRequestResource;
use App\Models\User;
use App\Models\WalletWithdrawalRequest;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Http\Resources\Wallet\WalletTransactionResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The customer's wallet in points (1 point = Rs 1 by default): balance, what is already promised to pending withdrawals,
 * the ledger and withdrawal requests. Same rules as taksh-admin; points come in as order rewards and refunds, and are paid
 * out to a bank account by admin.
 */
class WalletService
{
    public function __construct(private readonly WalletRepositoryInterface $wallet) {}

    /** @return array<string, mixed> */
    public function summary(User $user): array
    {
        $balance = $this->wallet->balance($user->id);
        $pending = $this->wallet->pendingWithdrawalPoints($user->id);
        $available = round(max(0, $balance - $pending), 2);

        $rate = (float) config('wallet.rupees_per_point', 1);
        $divisor = (int) config('wallet.order_reward_divisor', 100);

        return [
            'balance_points' => $balance,
            'balance_rupees_equivalent' => round($balance * $rate, 2),
            'pending_withdrawal_points' => $pending,
            'available_points' => $available,
            'available_rupees_equivalent' => round($available * $rate, 2),
            'min_withdrawal_points' => (int) config('wallet.min_withdrawal_points', 100),
            'rupees_per_point' => $rate,
            'order_reward' => [
                'points_formula' => "floor(order_total_rupees / {$divisor})",
                'example' => '₹1000 spent → '.(int) floor(1000 / $divisor).' point(s)',
            ],
        ];
    }

    /** The ledger (credits and debits), newest first. @return array{items: array, pagination: array<string, int>} */
    public function transactions(User $user, int $perPage): array
    {
        return $this->paged($this->wallet->transactions($user->id, $perPage), fn ($t) => WalletTransactionResource::make($t)->resolve());
    }

    /** @return array{items: array, pagination: array<string, int>} */
    public function withdrawals(User $user, int $perPage): array
    {
        return $this->paged($this->wallet->withdrawals($user->id, $perPage), fn ($w) => WithdrawalRequestResource::make($w)->resolve());
    }

    /**
     * Ask for a payout of points (at least the minimum, no more than what is available) to one of the customer's bank accounts.
     *
     * @return array<string, mixed> the new request
     */
    public function requestWithdrawal(User $user, float $points, int $bankDetailId): array
    {
        $min = (int) config('wallet.min_withdrawal_points', 100);
        $points = round($points, 2);

        if ($points < $min) {
            throw new CartProblemException('api.wallet_withdrawal_min_not_met', 422);
        }

        $row = $this->wallet->transaction(function () use ($user, $points, $bankDetailId) {
            // one customer's withdrawals are handled one after the other, so the same points cannot be promised twice
            $this->wallet->lockWallet($user->id);

            $available = max(0, $this->wallet->balance($user->id) - $this->wallet->pendingWithdrawalPoints($user->id));

            if ($points > round($available, 2)) {
                throw new CartProblemException('api.wallet_insufficient_points', 422);
            }

            return $this->wallet->createWithdrawal([
                'user_id' => $user->id,
                'bank_detail_id' => $bankDetailId,
                'points' => $points,
                'amount_rupees' => round($points * (float) config('wallet.rupees_per_point', 1), 2),
                'status' => WalletWithdrawalRequest::STATUS_PENDING,
            ]);
        });

        return WithdrawalRequestResource::make($row)->resolve();
    }

    /** I5: ledger lines that came from refunds. */
    public function refundTransactions(User $user): array
    {
        return $this->wallet->refundTransactions($user->id, 15)->through(fn ($t) => WalletTransactionResource::make($t)->resolve())->toArray();
    }

    private function paged(LengthAwarePaginator $page, callable $map): array
    {
        return [
            'items' => collect($page->items())->map($map)->values()->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ];
    }
}
