<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Shared `carts` table: one cart per user, or per guest_token until the guest logs in. */
class Cart extends Model
{
    protected $fillable = ['user_id', 'guest_token', 'coupon_code', 'coupon_delivery_type'];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
