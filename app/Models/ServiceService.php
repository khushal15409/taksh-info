<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A home service the admin offers (shared `service_services` table). */
class ServiceService extends Model
{
    use SoftDeletes;

    public const TYPE_PROVIDER = 'provider';

    protected $fillable = ['name', 'slug', 'description', 'short_description', 'base_price', 'service_charge', 'provider_share_percentage', 'platform_share_percentage', 'refund_type', 'refund_value', 'duration', 'image', 'images', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2', 'service_charge' => 'decimal:2', 'provider_share_percentage' => 'decimal:2',
            'platform_share_percentage' => 'decimal:2', 'refund_value' => 'decimal:2', 'images' => 'array',
            'is_active' => 'boolean', 'sort_order' => 'integer',
        ];
    }

    /** What the customer pays: only service_charge (0 when the admin has not set it). */
    public function getEffectiveChargeAttribute(): float
    {
        return (float) $this->service_charge;
    }
}
