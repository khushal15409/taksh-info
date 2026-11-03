<?php

namespace Tests\Support;

use App\Models\BankDetail;
use App\Models\WalletWithdrawalRequest;
use App\Repositories\Contracts\BankDetailRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\PresenceVerifierInterface;

class FakeWalletRepository implements WalletRepositoryInterface
{
    /** @var array<int, float> */
    public array $balances = [];

    public array $withdrawals = [];

    public Collection $ledger;

    public int $locked = 0;

    public int $lastPerPage = 0;

    public function __construct()
    {
        $this->ledger = collect();
    }

    public function transaction(Closure $callback): mixed
    {
        return $callback();
    }

    public function balance(int $userId): float
    {
        return (float) ($this->balances[$userId] ?? 0);
    }

    public function pendingWithdrawalPoints(int $userId): float
    {
        return (float) collect($this->withdrawals)->where('user_id', $userId)->where('status', 'pending')->sum('points');
    }

    public function lockWallet(int $userId): void
    {
        $this->locked++;
    }

    public function transactions(int $userId, int $perPage): LengthAwarePaginator
    {
        $this->lastPerPage = $perPage;

        return new Paginator($this->ledger->values(), $this->ledger->count(), $perPage, 1);
    }

    public function refundTransactions(int $userId, int $perPage): LengthAwarePaginator
    {
        $rows = $this->ledger->filter(fn ($t) => $t->refund_id);

        return new Paginator($rows->values(), $rows->count(), $perPage, 1);
    }

    public function withdrawals(int $userId, int $perPage): LengthAwarePaginator
    {
        $rows = collect($this->withdrawals)->where('user_id', $userId)->sortByDesc('id')->values();

        return new Paginator($rows, $rows->count(), $perPage, 1);
    }

    public function createWithdrawal(array $attributes): WalletWithdrawalRequest
    {
        $w = (new WalletWithdrawalRequest)->forceFill($attributes + ['id' => count($this->withdrawals) + 1, 'created_at' => now(), 'updated_at' => now()]);
        $w->setRelation('bankDetail', null);

        return $this->withdrawals[] = $w;
    }
}

class FakeBankDetailRepository implements BankDetailRepositoryInterface
{
    /** @var array<int, BankDetail> */
    public array $rows = [];

    public function forUser(int $userId): Collection
    {
        return collect($this->rows)->where('user_id', $userId)->sortByDesc('id')->values();
    }

    public function create(array $attributes): BankDetail
    {
        $b = (new BankDetail)->forceFill($attributes + ['id' => count($this->rows) + 1, 'created_at' => now(), 'updated_at' => now()]);

        return $this->rows[$b->id] = $b;
    }
}

/** `exists:bank_details,id` scoped to the owner: only a row of the same user counts. */
class FakeBankOwnershipVerifier implements PresenceVerifierInterface
{
    public function __construct(private readonly FakeBankDetailRepository $banks) {}

    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = []): int
    {
        $row = $this->banks->rows[(int) $value] ?? null;

        return $row && (! isset($extra['user_id']) || $row->user_id === (int) $extra['user_id']) ? 1 : 0;
    }

    public function getMultiCount($collection, $column, array $values, array $extra = []): int
    {
        return 0;
    }
}
