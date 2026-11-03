<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sale extends Model
{
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'sale_products', 'sale_id', 'product_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'sale_categories', 'sale_id', 'category_id');
    }

    public function fulfillmentCenters(): BelongsToMany
    {
        return $this->belongsToMany(FulfillmentCenter::class, 'sale_fulfillment_centers', 'sale_id', 'fulfillment_center_id');
    }

    /** Switched on and inside its start/end window. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('start_at', '<=', now())->where('end_at', '>=', now());
    }

    public function calculateDiscount($price): float
    {
        if ($this->discount_type === 'percentage') {
            return ($price * $this->discount_value) / 100;
        }

        return min($this->discount_value, $price); // a flat discount never exceeds the price
    }

    public function getDiscountLabel(): string
    {
        return $this->discount_type === 'percentage'
            ? $this->discount_value.'% OFF'
            : '₹'.number_format($this->discount_value, 2).' OFF';
    }
}
