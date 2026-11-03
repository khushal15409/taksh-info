<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Gateway refund for a CourierBooking payment. Amount is in rupees (like ServiceMarketplaceRefund). */
class CourierRefund extends Model
{
    protected $table = 'service_courier_refunds';

    protected $fillable = [
        'courier_booking_id', 'courier_payment_id', 'reference', 'gateway', 'gateway_refund_id',
        'amount', 'status', 'reason', 'failure_reason', 'initiated_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'initiated_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CourierBooking::class, 'courier_booking_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CourierPayment::class, 'courier_payment_id');
    }
}
