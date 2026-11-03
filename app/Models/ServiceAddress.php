<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Where a home service is needed (shared `service_addresses`; separate from the shop's delivery addresses). */
class ServiceAddress extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'name', 'mobile', 'address_line_1', 'address_line_2', 'landmark', 'pincode', 'city', 'state', 'latitude', 'longitude', 'type', 'is_default'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:8', 'longitude' => 'decimal:8', 'is_default' => 'boolean'];
    }
}
