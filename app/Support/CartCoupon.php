<?php

namespace App\Support;

use App\Models\Cart;

/** A coupon belongs to the bucket (standard / quick) it was applied on; carts from before that was recorded apply to either. */
final class CartCoupon
{
    public static function appliesToBucket(Cart $cart, bool $isQuick, bool $bucketColumnExists): bool
    {
        if (! $cart->coupon_code) {
            return false;
        }

        if (! $bucketColumnExists || $cart->coupon_delivery_type === null) {
            return true;
        }

        return $cart->coupon_delivery_type === ($isQuick ? 'express' : 'standard');
    }
}
