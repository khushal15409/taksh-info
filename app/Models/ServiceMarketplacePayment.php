<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One gateway attempt for a booking. `amount` is in paise. */
class ServiceMarketplacePayment extends Model
{
    protected $fillable = ['service_marketplace_booking_id', 'gateway', 'gateway_order_id', 'gateway_payment_id', 'amount', 'currency', 'status', 'method', 'failure_reason', 'raw_response', 'paid_at'];

    protected $hidden = ['raw_response'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'raw_response' => 'array', 'paid_at' => 'datetime'];
    }

    public function booking()
    {
        return $this->belongsTo(ServiceMarketplaceBooking::class, 'service_marketplace_booking_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
