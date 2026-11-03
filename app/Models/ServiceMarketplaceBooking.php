<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A prepaid home-service booking (shared `service_marketplace_bookings`). The admin confirms and assigns it. */
class ServiceMarketplaceBooking extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PROVIDER_ASSIGNED = 'provider_assigned';
    public const STATUS_PROVIDER_ACCEPTED = 'provider_accepted';
    public const STATUS_PROVIDER_REJECTED = 'provider_rejected';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** The only legal moves; the website only ever makes "-> cancelled". */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_PROVIDER_ASSIGNED, self::STATUS_CANCELLED],
        self::STATUS_PROVIDER_ASSIGNED => [self::STATUS_PROVIDER_ACCEPTED, self::STATUS_PROVIDER_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_PROVIDER_REJECTED => [self::STATUS_PROVIDER_ASSIGNED, self::STATUS_CANCELLED],
        self::STATUS_PROVIDER_ACCEPTED => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    /** Customer-facing groupings used by the history tabs. */
    public const CATEGORIES = [
        'pending' => [self::STATUS_PENDING],
        'upcoming' => [self::STATUS_CONFIRMED, self::STATUS_PROVIDER_ASSIGNED, self::STATUS_PROVIDER_ACCEPTED, self::STATUS_PROVIDER_REJECTED],
        'active' => [self::STATUS_IN_PROGRESS],
        'completed' => [self::STATUS_COMPLETED],
        'cancelled' => [self::STATUS_CANCELLED],
    ];

    protected $fillable = [
        'booking_number', 'user_id', 'service_service_id', 'service_address_id', 'pincode', 'address_snapshot',
        'customer_notes', 'scheduled_for', 'service_charge', 'provider_share_percentage', 'platform_share_percentage',
        'provider_amount', 'platform_amount', 'refund_type', 'refund_value', 'currency', 'status', 'payment_status',
        'paid_at', 'idempotency_key', 'service_provider_id', 'assigned_at', 'accepted_at', 'started_at', 'completed_at',
        'cancelled_at', 'cancelled_by_type', 'cancelled_by_id', 'cancellation_reason', 'refund_status',
    ];

    protected function casts(): array
    {
        return [
            'address_snapshot' => 'array', 'scheduled_for' => 'datetime', 'paid_at' => 'datetime', 'assigned_at' => 'datetime',
            'accepted_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
            'service_charge' => 'decimal:2', 'provider_share_percentage' => 'decimal:2', 'platform_share_percentage' => 'decimal:2',
            'provider_amount' => 'decimal:2', 'platform_amount' => 'decimal:2', 'refund_value' => 'decimal:2',
        ];
    }

    public function canMoveTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function service()
    {
        return $this->belongsTo(ServiceService::class, 'service_service_id');
    }

    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }

    public function assignments()
    {
        return $this->hasMany(ServiceMarketplaceBookingAssignment::class, 'service_marketplace_booking_id');
    }

    public function histories()
    {
        return $this->hasMany(ServiceMarketplaceBookingStatusHistory::class, 'service_marketplace_booking_id');
    }

    public function refund()
    {
        return $this->hasOne(ServiceMarketplaceRefund::class, 'service_marketplace_booking_id');
    }

    public function payments()
    {
        return $this->hasMany(ServiceMarketplacePayment::class, 'service_marketplace_booking_id');
    }

    public function latestPayment()
    {
        return $this->hasOne(ServiceMarketplacePayment::class, 'service_marketplace_booking_id')->latestOfMany();
    }
}
