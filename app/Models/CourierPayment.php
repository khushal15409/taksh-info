<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Razorpay payment attempt for a CourierBooking. Amount is in paise (like ServiceMarketplacePayment). */
class CourierPayment extends Model
{
    protected $table = 'service_courier_payments';

    protected $fillable = [
        'courier_booking_id', 'gateway', 'gateway_order_id', 'gateway_payment_id', 'amount',
        'currency', 'status', 'method', 'failure_reason', 'raw_response', 'paid_at',
    ];

    protected $hidden = ['raw_response'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'raw_response' => 'array', 'paid_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CourierBooking::class, 'courier_booking_id');
    }
}
