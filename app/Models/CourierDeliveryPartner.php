<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourierDeliveryPartner extends Model
{
    protected $table = 'service_courier_delivery_partners';

    protected $fillable = [
        'name',
        'slug',
        'base_charge',
        'charge_per_kg',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'base_charge' => 'decimal:2',
            'charge_per_kg' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(CourierBooking::class, 'courier_delivery_partner_id');
    }
}
