<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The delivery person's last known position for an order (updated from the rider's app). */
class DeliveryLocation extends Model
{
    protected $table = 'delivery_locations';

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'updated_at' => 'datetime'];
    }

    public function deliveryBoy(): BelongsTo
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_boy_id');
    }
}
