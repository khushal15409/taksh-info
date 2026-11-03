<?php

namespace App\Services\Orders;

use App\Helpers\AppCharges;
use App\Models\Cart;
use App\Models\Coupon;
use App\Services\CouponService;
use App\Services\PricingService;
use Illuminate\Support\Collection;

/** The extra charges and money summary of an order, always worked out on the server (never taken from the request). */
class OrderChargeCalculator
{
    public function __construct(private readonly CouponService $coupons, private readonly PricingService $pricing) {}

    /**
     * Platform / quick-delivery fee (once per basket) and the coupon discount, mirroring the cart bill. A coupon charge is
     * added only when the cart's coupon re-validates against the items total; an invalid one is simply dropped here.
     *
     * @param  bool  $withFees  the fees go on the first order of a basket only
     * @param  array|null|false  $couponShare  null: judge the coupon on $itemsTotal; array: this order's share of the basket coupon; false: no coupon
     * @return array{0: list<array<string, mixed>>, 1: ?array{coupon: Coupon, discount_amount: float}}
     */
    public function charges(Cart $cart, bool $couponApplies, float $itemsTotal, ?int $userId, bool $isQuick, bool $withCoupon, bool $withFees, array|null|false $couponShare = null): array
    {
        $fees = $withFees ? AppCharges::forOrder($isQuick) : ['platform_fee' => 0, 'quick_delivery_fee' => 0];
        $charges = [];

        if ($fees['platform_fee'] > 0) {
            $charges[] = ['type' => 'platform', 'label' => 'Platform Fees', 'amount' => $fees['platform_fee'], 'is_discount' => false];
        }
        if ($fees['quick_delivery_fee'] > 0) {
            $charges[] = ['type' => 'quick_delivery', 'label' => 'Quick Delivery Fee', 'amount' => $fees['quick_delivery_fee'], 'is_discount' => false];
        }

        $applied = null;

        if ($couponShare !== null) {
            if ($couponShare !== false && $couponShare['discount_amount'] > 0) {
                $charges[] = ['type' => 'coupon', 'label' => 'Coupon ('.$couponShare['coupon']->code.')', 'amount' => -$couponShare['discount_amount'], 'is_discount' => true];
                $applied = $couponShare;
            }
        } elseif ($withCoupon && $couponApplies) {
            $result = $this->coupons->resolve($cart->coupon_code, $userId, $itemsTotal);

            if ($result['valid']) {
                $charges[] = ['type' => 'coupon', 'label' => 'Coupon ('.$result['coupon']->code.')', 'amount' => -$result['discount_amount'], 'is_discount' => true];
                $applied = ['coupon' => $result['coupon'], 'discount_amount' => $result['discount_amount']];
            }
        }

        return [$charges, $applied];
    }

    /**
     * The basket's coupon discount, judged once on the whole basket (as the cart shows it) and split between the vendor
     * orders in proportion to their items. Null when the coupon no longer applies.
     * Keys: vendor id => ['coupon', 'discount_amount'], plus '_total'.
     *
     * @param  Collection<int, Collection>  $byVendor  cart lines grouped by vendor id
     */
    public function basketCouponShares(Cart $cart, Collection $byVendor, int $userId): ?array
    {
        $subtotals = $byVendor->map(fn ($items) => round($items->sum(function ($item) {
            $variant = $item->productVariant;

            return round($this->pricing->calculateCheckoutPrice($variant->product, $variant)['final_sell_price'] * $item->qty, 2);
        }), 2));
        $basketTotal = round($subtotals->sum(), 2);

        $result = $this->coupons->resolve($cart->coupon_code, $userId, $basketTotal);
        if (! $result['valid'] || $basketTotal <= 0) {
            return null;
        }

        $total = round((float) $result['discount_amount'], 2);
        $shares = ['_total' => $total];
        $given = 0.0;
        $lastVendor = $subtotals->keys()->last();

        foreach ($subtotals as $vendorId => $subtotal) {
            $share = $vendorId === $lastVendor ? round($total - $given, 2) : round($total * $subtotal / $basketTotal, 2);
            $given = round($given + $share, 2);
            $shares[$vendorId] = ['coupon' => $result['coupon'], 'discount_amount' => $share];
        }

        return $shares;
    }

    /**
     * The money summary saved on the order row, so a refund or a payout never has to add the bill up again.
     *
     * @return array<string, float>
     */
    public function totals(array $orderItems, array $charges, float $grandTotal): array
    {
        $chargeOf = function (string $type) use ($charges) {
            foreach ($charges as $charge) {
                if ($charge['type'] === $type) {
                    return (float) $charge['amount'];
                }
            }

            return 0.0;
        };

        $items = 0.0;
        $taksh = 0.0;
        foreach ($orderItems as $item) {
            $qty = (int) $item['qty'];
            $taksh += (float) ($item['taksh_charge'] ?? 0) * $qty;
            $items += ((float) $item['price'] - (float) ($item['taksh_charge'] ?? 0)) * $qty;
        }

        return [
            'items_total' => round($items, 2),
            'taksh_charge_total' => round($taksh, 2),
            'platform_fee' => $chargeOf('platform'),
            'quick_delivery_fee' => $chargeOf('quick_delivery'),
            'discount_total' => round(abs($chargeOf('coupon')), 2),
            'grand_total' => round($grandTotal, 2),
        ];
    }
}
