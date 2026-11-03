<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class CourierBooking extends Model
{
    protected $table = 'service_courier_bookings';

    protected $fillable = [
        'booking_number',
        'user_id',
        'customer_name',
        'customer_mobile',
        'pickup_address',
        'pickup_pincode',
        'delivery_name',
        'delivery_mobile',
        'delivery_address',
        'delivery_pincode',
        'weight_grams',
        'dimension_unit',
        'length',
        'width',
        'height',
        'volumetric_weight_grams',
        'chargeable_weight_grams',
        'chargeable_weight_source',
        'customer_notes',
        'product_details',
        'packaging_details',
        'item_photo_path',
        'courier_delivery_partner_id',
        'total_amount',
        'status',
        'payment_status',
        'payment_gateway',
        'payment_order_id',
        'payment_transaction_id',
        'paid_at',
        'shiprocket_order_id',
        'shiprocket_shipment_id',
        'shiprocket_courier_company_id',
        'awb_code',
        'courier_name',
        'shipment_status',
        'pickup_status',
        'shiprocket_estimated_cost',
        'platform_charge',
        'actual_shiprocket_cost',
        'tracking_url',
        'shiprocket_estimated_delivery_days',
        'cancelled_at',
        'cancelled_by_type',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'weight_grams' => 'decimal:3',
            'length' => 'decimal:4',
            'width' => 'decimal:4',
            'height' => 'decimal:4',
            'volumetric_weight_grams' => 'decimal:3',
            'chargeable_weight_grams' => 'decimal:3',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'shiprocket_estimated_cost' => 'decimal:2',
            'platform_charge' => 'decimal:2',
            'actual_shiprocket_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CourierBooking $booking) {
            if (empty($booking->booking_number)) {
                $booking->booking_number = 'CRB-' . strtoupper(Str::random(8));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(CourierDeliveryPartner::class, 'courier_delivery_partner_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CourierPayment::class, 'courier_booking_id');
    }

    /** One Shiprocket shipment per booking (unique courier_booking_id). */
    public function shipment(): HasOne
    {
        return $this->hasOne(CourierShipment::class, 'courier_booking_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(CourierRefund::class, 'courier_booking_id');
    }
}
