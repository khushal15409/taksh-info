<?php

namespace App\Services;

use App\Models\Coupon;
use App\Repositories\Contracts\CouponRepositoryInterface;

/**
 * Decides whether a coupon discount is granted. Always called with server-known values (the cart's own
 * item total, the signed-in customer) - never a discount amount sent by the browser.
 */
class CouponService
{
    public function __construct(private readonly CouponRepositoryInterface $coupons) {}

    /** @return array{valid: bool, message: ?string, coupon: ?Coupon, discount_amount: float} */
    public function resolve(string $code, ?int $userId, float $cartTotal): array
    {
        $coupon = $this->coupons->findByCode(strtoupper(trim($code)));

        if (! $coupon) {
            return $this->invalid('Invalid coupon code');
        }
        if (! $coupon->is_active) {
            return $this->invalid('This coupon is no longer active');
        }

        $now = now();
        if ($coupon->start_at && $now->lt($coupon->start_at)) {
            return $this->invalid('This coupon is not active yet');
        }
        if ($coupon->end_at && $now->gt($coupon->end_at)) {
            return $this->invalid('This coupon has expired');
        }
        if ($cartTotal < (float) $coupon->min_cart_value) {
            return $this->invalid('Minimum cart value of '.number_format((float) $coupon->min_cart_value, 2).' required for this coupon');
        }
        if ($coupon->usage_limit !== null && $this->coupons->usageCount($coupon) >= $coupon->usage_limit) {
            return $this->invalid('This coupon has reached its usage limit');
        }
        if ($userId !== null && $coupon->usage_limit_per_user !== null && $this->coupons->userUsageCount($coupon, $userId) >= $coupon->usage_limit_per_user) {
            return $this->invalid('You have already used this coupon the maximum number of times');
        }

        return ['valid' => true, 'message' => null, 'coupon' => $coupon, 'discount_amount' => $coupon->calculateDiscount($cartTotal)];
    }

    private function invalid(string $message): array
    {
        return ['valid' => false, 'message' => $message, 'coupon' => null, 'discount_amount' => 0.0];
    }
}
