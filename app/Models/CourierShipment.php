<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Shiprocket shipment lifecycle for a CourierBooking (one row per booking). */
class CourierShipment extends Model
{
    protected $table = 'service_courier_shipments';

    protected $fillable = [
        'courier_booking_id', 'provider', 'channel_order_id', 'shiprocket_order_id', 'shiprocket_shipment_id',
        'courier_company_id', 'courier_name', 'awb_code', 'pickup_status', 'shipment_status',
        'tracking_url', 'estimated_cost', 'actual_cost', 'failure_reason',
    ];

    protected function casts(): array
    {
        return ['estimated_cost' => 'decimal:2', 'actual_cost' => 'decimal:2'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CourierBooking::class, 'courier_booking_id');
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(CourierTrackingEvent::class, 'courier_shipment_id');
    }
}
