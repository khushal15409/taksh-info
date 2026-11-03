<?php

namespace App\Services\Orders;

use App\Exceptions\CartProblemException;
use App\Helpers\Money;
use App\Helpers\PriceBreakup;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Contracts\CartCatalogRepositoryInterface;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\CheckoutRepositoryInterface;
use App\Services\PricingService;
use App\Support\CartCoupon;
use App\Support\DeliveryBucket;
use App\Support\PaymentOptions;
use Illuminate\Support\Str;

/**
 * Place order, step for step as taksh-admin's OrderController::store, for Standard delivery: the cart is locked first
 * (a double submit cannot create a second order), every line is checked, vendor stock is reserved under row locks,
 * one order is made per shop, fees and the coupon are worked out on the server and a payment record is created.
 * Quick (30 minute) orders are placed in the app for now.
 */
class OrderPlacementService
{
    public function __construct(
        private readonly CheckoutRepositoryInterface $checkout,
        private readonly CartRepositoryInterface $carts,
        private readonly CartCatalogRepositoryInterface $catalog,
        private readonly PricingService $pricing,
        private readonly OrderChargeCalculator $charges,
        private readonly OrderNotifier $notifier,
    ) {}

    /**
     * @param  array{address_id: int, delivery_type: string, payment_method: string}  $input
     * @return array{order: Order, placed: list<Order>}
     */
    public function place(User $user, array $input): array
    {
        $deliveryCode = $input['delivery_type'];
        $method = $input['payment_method'];

        // the admin can switch a way of paying off for a kind of order; refuse it here too
        if (! PaymentOptions::enabled(PaymentOptions::orderTypeFor($deliveryCode), $method)) {
            throw new CartProblemException('This payment method is not available for this order.', 400, ['errors' => ['payment_method' => ['This payment method is not available for this order.']]]);
        }

        $address = $this->checkout->address($user->id, (int) $input['address_id']) ?? throw new CartProblemException('api.address_not_found', 404);
        $this->catalog->deliveryType($deliveryCode) ?? throw new CartProblemException('api.delivery_type_invalid', 400);

        // the delivery type decides which part of the cart is ordered; the other kind of item stays in the cart untouched
        $isQuick = DeliveryBucket::isQuick($deliveryCode);
        if ($isQuick) {
            throw CartProblemException::quickOnApp();
        }

        $placed = $this->checkout->transaction(fn () => $this->placeInTransaction($user, $address, $deliveryCode, $method, $isQuick));

        // telling the shop is an extra: the order is already placed and paid for as it stands, so a failure here must not look like a failed order
        try {
            $this->notifier->placed($placed);
        } catch (\Throwable $e) {
            report($e);
        }

        return ['order' => end($placed), 'placed' => $placed];
    }

    /** @return list<Order> */
    private function placeInTransaction(User $user, $address, string $deliveryCode, string $method, bool $isQuick): array
    {
        $cart = $this->checkout->lockedCart($user->id);
        $allItems = $cart ? $this->checkout->cartItems($cart) : collect();

        if ($allItems->isEmpty()) {
            throw CartProblemException::emptyCart();
        }

        // a line whose product or variant no longer exists cannot be ordered and must not be skipped silently
        foreach ($allItems as $line) {
            if (! $line->productVariant || ! $line->productVariant->product) {
                throw CartProblemException::notAvailable();
            }
        }

        $cartItems = $allItems->filter(fn ($i) => (bool) $i->productVariant->product->is_express_30 === $isQuick)->values();

        if ($cartItems->isEmpty()) {
            throw new CartProblemException('api.cart_no_items_for_delivery_type', 422);
        }

        // every line needs a vendor; a cart holding several shops' items becomes one order per shop
        foreach ($cartItems as $line) {
            if (! $line->productVariant->product->vendor_id) {
                throw new CartProblemException('api.product_vendor_not_set', 400);
            }
        }

        $byVendor = $cartItems->groupBy(fn ($i) => $i->productVariant->product->vendor_id);

        // orders placed together share a group id so one online payment can settle all of them; without the column a
        // multi-vendor cart can only be paid on delivery
        $groupId = null;
        if ($byVendor->count() > 1) {
            if ($this->checkout->hasCheckoutGroupColumn()) {
                $groupId = (string) Str::uuid();
            } elseif ($method === 'online') {
                throw new CartProblemException('api.multi_vendor_online_unsupported', 422);
            }
        }

        $couponApplies = CartCoupon::appliesToBucket($cart, $isQuick, $this->carts->couponBucketColumnExists());

        // the cart shows ONE fee and ONE coupon for the whole basket, so the orders together must cost exactly that:
        // fees go on the first order and the coupon discount is shared between the orders in proportion to what each buys
        $couponShares = null;
        if ($byVendor->count() > 1) {
            if ($couponApplies) {
                $this->checkout->lockCoupon($cart->coupon_code);
            }
            $couponShares = $couponApplies ? ($this->charges->basketCouponShares($cart, $byVendor, $user->id) ?? []) : [];
        }

        $placed = [];
        $couponUsed = false;

        foreach ($byVendor as $vendorId => $groupItems) {
            $vendor = $this->checkout->vendor($vendorId) ?? throw new CartProblemException('api.vendor_not_found', 404);

            // only an approved vendor sells, even if the products were put into the cart before it was disabled
            if ($vendor->status !== 'approved') {
                throw new CartProblemException('api.vendor_not_available', 422);
            }

            // standard delivery: the address pincode must be one the vendor serves (the same rule as the product page check)
            if (! $vendor->servesPincode($address->pincode)) {
                throw new CartProblemException('api.delivery_not_serviceable', 422);
            }

            // lock the vendor's stock rows: the check and the reservation cannot interleave with another order
            $stockRows = $this->checkout->lockedStock($vendor->id, $groupItems->pluck('product_variant_id')->all());

            $orderItems = [];
            $itemsTotal = 0.0;

            foreach ($groupItems as $line) {
                $variant = $line->productVariant;
                $product = $variant->product;

                if (! $product->vendor_id || $product->vendor_id != $vendor->id) {
                    throw new CartProblemException('api.product_not_belongs_to_vendor', 400);
                }
                // a product or variant deactivated after it went into the cart can no longer be ordered
                if ($variant->status !== 'active' || $product->status !== 'active') {
                    throw CartProblemException::notAvailable();
                }

                $row = $stockRows->get($variant->id) ?? throw new CartProblemException('Product not available from vendor', 400);

                if (($row->stock_qty ?? 0) - ($row->reserved_qty ?? 0) < $line->qty) {
                    throw new CartProblemException('api.insufficient_stock', 400);
                }

                $this->checkout->reserveStock($row, $line->qty);

                $price = $this->pricing->calculateCheckoutPrice($product, $variant)['final_sell_price'];
                // the same paisa rounding as the cart view, so the cart total and the order total agree
                $itemsTotal = round($itemsTotal + round($price * $line->qty, 2), 2);

                // the order keeps its own copy of the money, so a later price change cannot move an old payout or refund
                $orderItems[] = array_merge(
                    ['product_variant_id' => $variant->id, 'qty' => $line->qty, 'price' => $price],
                    $this->checkout->itemSnapshot($product, $variant),
                    PriceBreakup::forOrderItem($variant, $product),
                );
            }

            // lock the coupon row so concurrent orders cannot both pass the usage limit
            if ($couponShares === null && ! $couponUsed && $couponApplies) {
                $this->checkout->lockCoupon($cart->coupon_code);
            }

            $isFirst = $placed === [];
            [$extra, $appliedCoupon] = $this->charges->charges(
                $cart, $couponApplies, $itemsTotal, $user->id, $isQuick, ! $couponUsed, $isFirst,
                $couponShares === null ? null : ($couponShares[$vendorId] ?? false),
            );
            $grandTotal = $itemsTotal + collect($extra)->sum(fn ($c) => (float) ($c['amount'] ?? 0));

            $order = $this->checkout->createOrder(array_merge([
                'user_id' => $user->id,
                'vendor_id' => $vendor->id,
                'address_id' => $address->id,
                'order_number' => 'ORD'.strtoupper(Str::random(8)),
                'delivery_type' => $deliveryCode,
                'order_status' => 'pending',
                'order_type' => 'fm',
            ], $groupId ? ['checkout_group_id' => $groupId] : [], $this->charges->totals($orderItems, $extra, $grandTotal)));

            foreach ($extra as $charge) {
                $this->checkout->createCharge($order, $charge);
            }

            // counts as a use from now on (it reserves the coupon for this order); handed back if the order is cancelled
            if ($appliedCoupon && $isFirst) {
                $couponUsed = true;
                $this->checkout->recordCouponUsage($appliedCoupon['coupon']->id, $user->id, $order->id, $couponShares === null ? $appliedCoupon['discount_amount'] : $couponShares['_total']);
            }

            // the amount is the exact paise of the same 2-decimal total that is saved as grand_total
            $this->checkout->createPayment([
                'order_id' => $order->id,
                'gateway' => $method === 'online' ? 'razorpay' : 'cod',
                'amount' => Money::toMinorUnits(round($grandTotal, 2)),
                'currency' => 'INR',
                'status' => 'pending',
                'method' => $method,
            ]);

            foreach ($orderItems as $item) {
                $this->checkout->createItem($order, $item);
            }

            $placed[] = $order;
        }

        // remove only the lines that were ordered; the cart goes when nothing is left, and the used coupon is cleared from a cart that remains
        $this->checkout->removeCartLines($cart, $cartItems->pluck('id')->all());
        if (! $this->checkout->cartHasItems($cart)) {
            $this->checkout->deleteCart($cart);
        } elseif ($couponApplies) {
            $this->carts->setCoupon($cart, null, null);
        }

        return $placed;
    }
}
