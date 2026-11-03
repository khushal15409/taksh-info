<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A code-gated cart discount (never applies unless the customer supplies its exact code). */
class Coupon extends Model
{
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'min_cart_value' => 'decimal:2',
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** Percentage discounts are capped at max_discount_amount (when set); no discount is ever more than the subtotal. */
    public function calculateDiscount(float $cartTotal): float
    {
        $discount = $this->discount_type === 'percentage'
            ? $cartTotal * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return round(min($discount, $cartTotal), 2);
    }
}
