<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceMarketplaceBookingStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['service_marketplace_booking_id', 'from_status', 'to_status', 'changed_by_type', 'changed_by_id', 'reason'];
}
