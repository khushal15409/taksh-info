<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'razorpay_order_id', 'razorpay_payment_id', 'transaction_id', 'gateway', 'amount', 'currency', 'status', 'method', 'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',      // paise
            'raw_response' => 'array',
        ];
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'success'], true);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
