<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Normalized tracking event for a CourierShipment. */
class CourierTrackingEvent extends Model
{
    protected $table = 'service_courier_tracking_events';

    protected $fillable = [
        'courier_shipment_id', 'external_event_id', 'dedupe_key', 'status', 'status_code',
        'description', 'location', 'event_time', 'normalized_status', 'source',
    ];

    protected function casts(): array
    {
        return ['event_time' => 'datetime'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(CourierShipment::class, 'courier_shipment_id');
    }
}
