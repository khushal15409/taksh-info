<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletWithdrawalRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    protected $fillable = ['user_id', 'bank_detail_id', 'points', 'amount_rupees', 'status', 'rejection_reason', 'processed_at'];

    protected function casts(): array
    {
        return ['points' => 'decimal:2', 'amount_rupees' => 'decimal:2', 'processed_at' => 'datetime'];
    }

    public function bankDetail(): BelongsTo
    {
        return $this->belongsTo(BankDetail::class, 'bank_detail_id');
    }
}
