<?php

namespace App\Repositories\Contracts;

use App\Models\WalletWithdrawalRequest;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface WalletRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    /** Completed credits minus completed debits, in points. */
    public function balance(int $userId): float;

    /** Points already promised to withdrawal requests that are still pending. */
    public function pendingWithdrawalPoints(int $userId): float;

    /** Serialise wallet changes of one customer (two withdrawals at the same moment cannot both spend the same points). */
    public function lockWallet(int $userId): void;

    public function transactions(int $userId, int $perPage): LengthAwarePaginator;

    /** The ledger lines that came from a refund (with the refund and its return). */
    public function refundTransactions(int $userId, int $perPage): LengthAwarePaginator;

    public function withdrawals(int $userId, int $perPage): LengthAwarePaginator;

    public function createWithdrawal(array $attributes): WalletWithdrawalRequest;
}
