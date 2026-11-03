<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceMarketplaceRefund extends Model
{
    public const NOT_REQUIRED = 'not_required';
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';

    protected $fillable = [
        'service_marketplace_booking_id', 'service_marketplace_payment_id', 'reference', 'gateway', 'gateway_payment_id',
        'gateway_refund_id', 'paid_amount', 'requested_amount', 'refunded_amount', 'non_refundable_amount',
        'refund_type', 'refund_value', 'status', 'reason', 'requested_by_type', 'requested_by_id', 'failure_reason', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_amount' => 'decimal:2', 'requested_amount' => 'decimal:2', 'refunded_amount' => 'decimal:2',
            'non_refundable_amount' => 'decimal:2', 'refund_value' => 'decimal:2', 'processed_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(ServiceMarketplaceBooking::class, 'service_marketplace_booking_id');
    }
}
