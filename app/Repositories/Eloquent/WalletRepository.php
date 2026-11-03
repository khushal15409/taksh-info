<?php

namespace App\Repositories\Eloquent;

use App\Models\WalletTransaction;
use App\Models\WalletWithdrawalRequest;
use App\Repositories\Contracts\WalletRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WalletRepository implements WalletRepositoryInterface
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function balance(int $userId): float
    {
        $sum = fn (string $type) => (float) WalletTransaction::where('user_id', $userId)->where('status', 'completed')->where('type', $type)->sum('amount');

        return round($sum('credit') - $sum('debit'), 2);
    }

    public function pendingWithdrawalPoints(int $userId): float
    {
        return (float) WalletWithdrawalRequest::where('user_id', $userId)->where('status', WalletWithdrawalRequest::STATUS_PENDING)->sum('points');
    }

    public function lockWallet(int $userId): void
    {
        DB::table('users')->where('id', $userId)->lockForUpdate()->first(['id']);
    }

    public function transactions(int $userId, int $perPage): LengthAwarePaginator
    {
        return WalletTransaction::where('user_id', $userId)
            ->with(['refund', 'order' => fn ($q) => $q->select('id', 'order_number', 'order_status'), 'walletWithdrawalRequest'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function refundTransactions(int $userId, int $perPage): LengthAwarePaginator
    {
        return WalletTransaction::where('user_id', $userId)->with(['refund.return'])->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function withdrawals(int $userId, int $perPage): LengthAwarePaginator
    {
        return WalletWithdrawalRequest::where('user_id', $userId)->with('bankDetail')->orderByDesc('created_at')->paginate($perPage);
    }

    public function createWithdrawal(array $attributes): WalletWithdrawalRequest
    {
        return WalletWithdrawalRequest::create($attributes)->load('bankDetail');
    }
}
