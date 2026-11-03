<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceMarketplaceBookingAssignment extends Model
{
    protected $fillable = ['service_marketplace_booking_id', 'service_provider_id', 'assigned_by', 'status', 'rejection_reason', 'assigned_at', 'responded_at'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'responded_at' => 'datetime'];
    }
}
