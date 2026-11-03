<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of the wallet ledger. Points: a credit adds, a debit takes away; only `completed` lines count. */
class WalletTransaction extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function walletWithdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(WalletWithdrawalRequest::class, 'wallet_withdrawal_request_id');
    }
}
