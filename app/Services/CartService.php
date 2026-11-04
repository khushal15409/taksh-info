<?php

namespace App\Services;

use App\Exceptions\CartProblemException;
use App\Helpers\AppCharges;
use App\Http\Resources\Cart\CartItemResource;
use App\Http\Resources\Cart\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Repositories\Contracts\CartCatalogRepositoryInterface;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Support\CartCoupon;
use App\Support\CartOwner;
use App\Support\DeliveryBucket;

/**
 * The shopping cart as taksh-admin's CartController runs it: Standard and Quick products in separate carts,
 * stock-checked lines, a server-built bill (fees, coupon) and a guest cart that follows the customer after login.
 * The Quick bucket works like the Standard one; a Quick product is added only for a place a shop serves with stock to give (QuickAvailability).
 */
class CartService
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly CartCatalogRepositoryInterface $catalog,
        private readonly \App\Repositories\Contracts\CatalogRepositoryInterface $stockCatalog,
        private readonly PricingService $pricing,
        private readonly CouponService $coupons,
        private readonly GuestCartSession $guest,
        private readonly \App\Services\Quick\QuickAvailability $quick,
    ) {}

    // ---------------------------------------------------------------- add (D2)

    /** @return array{cart: array, guest_token: ?string} */
    /**
     * @param  array{pincode: ?string, latitude: ?float, longitude: ?float}|null  $where  the place a Quick product is for (checked when it is known;
     *                                                                                  the order checks it again either way)
     */
    public function add(CartOwner $owner, int $variantId, int $qty, ?string $deliveryType = null, ?array $where = null): array
    {
        $variant = $this->catalog->variant($variantId) ?? throw CartProblemException::notAvailable();
        $product = $variant->product;
        $productIsQuick = (bool) ($product?->is_express_30);

        // A product only goes into the bucket its own delivery type belongs to, never silently into the other one.
        if (DeliveryBucket::given($deliveryType) && DeliveryBucket::isQuick($deliveryType) !== $productIsQuick) {
            throw CartProblemException::bucketMismatch($productIsQuick);
        }

        // a Quick product is only added for a place a shop serves with stock to give, just as in the app
        if ($productIsQuick && $where && ($where['pincode'] !== null || $where['latitude'] !== null)) {
            $product->loadMissing('variants');
            $verdict = $this->quick->check($product, $where['latitude'], $where['longitude'], $where['pincode'], $variantId);
            if (! $verdict['can_order']) {
                throw CartProblemException::quickUnavailable($verdict['status'], \App\Services\Quick\QuickAvailability::messageKey($verdict['status']));
            }
        }

        $cart = $owner->isGuest()
            ? $this->carts->firstOrCreateForGuest($this->guest->ensure())
            : $this->carts->firstOrCreateForUser($owner->userId);

        $this->carts->addLine($cart, $variant, $qty, fn (int $newQty) => $this->assertCanHold($variant, $newQty));

        return ['cart' => $this->cartData($cart, $deliveryType), 'guest_token' => $cart->guest_token];
    }

    // ---------------------------------------------------------------- buy now

    /**
     * "Buy Now": the bill of ONE product, not the cart. Nothing is saved - the line exists only for this bill, so the customer's cart is
     * neither read nor changed. Quick products are billed in the Quick bucket, the others in Standard; a cart coupon does not apply.
     *
     * @return array{type: string, cart: array}
     */
    public function buyNow(int $userId, int $variantId, int $qty): array
    {
        $variant = $this->catalog->variant($variantId) ?? throw CartProblemException::notAvailable();
        $product = $variant->product ?? throw CartProblemException::notAvailable();
        $type = $product->is_express_30 ? '30_min' : 'normal';

        $line = (new CartItem)->forceFill(['product_variant_id' => $variant->id, 'qty' => max(1, min($qty, 99))]);
        $line->setRelation('productVariant', $variant);

        return ['type' => $type, 'cart' => $this->cartData((new Cart)->forceFill(['user_id' => $userId]), $type, collect([$line]))];
    }

    // ---------------------------------------------------------------- read (D1, D5)

    /** @return array{cart: array, guest_token: ?string} */
    public function get(CartOwner $owner, string $deliveryType): array
    {
        $cart = $this->cartOf($owner);

        if (! $cart) {
            return ['cart' => $this->emptyData($deliveryType), 'guest_token' => $owner->isGuest() ? $owner->guestToken : null];
        }

        return ['cart' => $this->cartData($cart, $deliveryType), 'guest_token' => $cart->guest_token];
    }

    // ---------------------------------------------------------------- change lines (D2)

    public function update(CartOwner $owner, int $itemId, int $qty, ?string $deliveryType = null): array
    {
        $item = $this->owned($owner, $itemId);

        $updated = $this->carts->setLineQty($item, $qty, function (CartItem $locked) use ($qty) {
            $variant = $this->catalog->variant($locked->product_variant_id) ?? throw new CartProblemException('api.product_not_available');
            $this->assertCanHold($variant, $qty);
        });

        if (! $updated) {
            throw CartProblemException::notFoundInCart();
        }

        return ['cart' => $this->cartData($item->cart, $deliveryType)];
    }

    public function remove(CartOwner $owner, int $itemId, ?string $deliveryType = null): array
    {
        $item = $this->owned($owner, $itemId);
        $cart = $item->cart;
        $this->carts->deleteItem($item);

        return ['cart' => $this->cartData($cart, $deliveryType)];
    }

    /** D3: removes the lines of one bucket (none = the whole cart). Clearing an empty or missing cart succeeds. */
    public function clear(CartOwner $owner, ?string $deliveryType = null): array
    {
        $cart = $this->cartOf($owner);

        if (! $cart) {
            return [];
        }

        $this->carts->clear($cart, $deliveryType);

        return ['cart' => $this->cartData($cart, $deliveryType)];
    }

    // ---------------------------------------------------------------- coupons (D6)

    public function applyCoupon(CartOwner $owner, string $code, ?string $deliveryType = null): array
    {
        $cart = $this->nonEmptyCart($owner);

        // judged on the same items the cart shows and the order will buy: the delivery bucket (the whole cart when none is given)
        $result = $this->coupons->resolve($code, $owner->userId, $this->itemsTotal($cart, $deliveryType));

        if (! $result['valid']) {
            throw CartProblemException::coupon($result['message']);
        }

        $this->carts->setCoupon($cart, $result['coupon']->code, DeliveryBucket::of($deliveryType));

        return ['cart' => $this->cartData($cart, $deliveryType), 'guest_token' => $cart->guest_token];
    }

    public function removeCoupon(CartOwner $owner, ?string $deliveryType = null): array
    {
        $cart = $this->nonEmptyCart($owner);
        $this->carts->setCoupon($cart, null, null);

        return ['cart' => $this->cartData($cart, $deliveryType), 'guest_token' => $cart->guest_token];
    }

    // ---------------------------------------------------------------- internals

    private function cartOf(CartOwner $owner): ?Cart
    {
        if ($owner->userId !== null) {
            return $this->carts->forUser($owner->userId);
        }

        return $owner->guestToken ? $this->carts->forGuest($owner->guestToken) : null;
    }

    private function owned(CartOwner $owner, int $itemId): CartItem
    {
        return $this->carts->findOwnedItem($itemId, $owner->userId, $owner->guestToken) ?? throw CartProblemException::notFoundInCart();
    }

    private function nonEmptyCart(CartOwner $owner): Cart
    {
        $cart = $this->cartOf($owner);

        if (! $cart || ! $this->carts->hasItems($cart)) {
            throw CartProblemException::emptyCart();
        }

        return $cart;
    }

    /** Can $qty of this variant be bought: it, its product and its vendor are live and there is stock left. */
    private function assertCanHold(ProductVariant $variant, int $qty): void
    {
        $product = $variant->product;

        if ($variant->status !== 'active' || ! $product || $product->status !== 'active') {
            throw CartProblemException::notAvailable();
        }
        if ($product->is_express_30 && $product->approval_status !== 'approved') {
            throw CartProblemException::notAvailable();
        }
        if (! $product->vendor_id) {
            throw CartProblemException::vendorNotSet();
        }
        if (! $this->catalog->vendorApproved($product->vendor_id)) {
            throw CartProblemException::vendorUnavailable();
        }

        $available = $this->catalog->availableStock($product->vendor_id, $variant->id);
        if ($qty > $available) {
            throw CartProblemException::insufficientStock($available, (bool) $product->is_express_30);
        }
    }

    /** Sum of item prices only (no fees): what a coupon's minimum and percentage are worked out against. */
    private function itemsTotal(Cart $cart, ?string $deliveryType): float
    {
        $total = 0.0;

        foreach ($this->carts->items($cart, $deliveryType) as $item) {
            $variant = $item->productVariant;
            $total += $this->pricing->calculateCheckoutPrice($variant->product, $variant)['final_sell_price'] * $item->qty;
        }

        return $total;
    }

    /** Nothing to buy: no items, no fees, nothing to pay. Delivery is shown as free like a cart with items. */
    private function emptyData(?string $deliveryType): array
    {
        $type = DeliveryBucket::given($deliveryType) ? $this->catalog->deliveryType($deliveryType) : null;

        return CartResource::make((object) [
            'items' => [], 'total' => 0, 'charges' => [], 'coupon' => null, 'total_with_delivery' => 0,
            'delivery' => $type ? ['code' => $type->code, 'estimated_minutes' => $type->estimated_minutes, 'price' => 0] : null,
        ])->resolve();
    }

    private function cartData(Cart $cart, ?string $deliveryType, ?\Illuminate\Support\Collection $only = null): array
    {
        $items = $only ?? $this->carts->items($cart, $deliveryType);

        if ($items->isEmpty()) {
            return $this->emptyData($deliveryType);
        }

        $vendorIds = $items->map(fn ($i) => $i->productVariant->product->vendor_id)->filter()->unique()->values()->all();
        $stock = $this->stockCatalog->vendorStock($items->pluck('product_variant_id')->all());
        $approved = $this->catalog->approvedVendorIds($vendorIds);
        $names = $this->catalog->vendorNames($vendorIds);

        $total = 0.0;
        $rows = $items->map(function (CartItem $item) use (&$total, $stock, $approved, $names) {
            $variant = $item->productVariant;
            $product = $variant->product;
            $vp = $product->vendor_id ? $stock->get($product->vendor_id.'_'.$variant->id) : null;
            $available = $vp ? max(0, (int) $vp->stock_qty - (int) $vp->reserved_qty) : 0;
            $pricing = $this->pricing->calculateCheckoutPrice($product, $variant);
            // money is kept to the paisa: a line is unit price x qty rounded to 2 decimals, the total is the sum of lines
            $lineTotal = round($pricing['final_sell_price'] * $item->qty, 2);
            $total = round($total + $lineTotal, 2);

            return CartItemResource::make((object) [
                'item' => $item, 'variant' => $variant, 'product' => $product, 'pricing' => $pricing,
                'line_total' => $lineTotal, 'available_stock' => $available,
                'vendor_name' => $names->get($product->vendor_id),
                // can this line be ordered right now: variant, product and vendor active, stock left
                'in_stock' => $variant->status === 'active' && $product->status === 'active' && $approved->contains($product->vendor_id) && $available > 0,
            ])->resolve();
        })->all();

        [$charges, $coupon] = $this->charges($cart, $total, DeliveryBucket::isQuick($deliveryType));
        $fees = collect($charges)->sum('amount');

        $type = DeliveryBucket::given($deliveryType) ? $this->catalog->deliveryType($deliveryType) : null;

        return CartResource::make((object) [
            'items' => $rows,
            'total' => round($total, 2),
            // delivery is paid for by the Taksh charge in each product price, so it is shown as free rather than added again
            'delivery' => $type ? ['code' => $type->code, 'estimated_minutes' => $type->estimated_minutes, 'price' => 0] : null,
            'charges' => $charges,
            'coupon' => $coupon,
            'total_with_delivery' => round($total + $fees, 2),
        ])->resolve();
    }

    /**
     * Fees admin switched on, plus the coupon when the cart has one that still validates for this bucket.
     * An applied coupon that stopped being valid (expired, total dropped below the minimum) is cleared from the cart.
     *
     * @return array{0: array, 1: ?array} [charges, coupon info]
     */
    private function charges(Cart $cart, float $total, bool $isQuick): array
    {
        $fees = AppCharges::forOrder($isQuick);
        $charges = [];

        if ($fees['platform_fee'] > 0) {
            $charges[] = ['type' => 'platform', 'label' => 'Platform Fees', 'amount' => $fees['platform_fee'], 'is_discount' => false, 'sort_order' => 4];
        }
        if ($fees['quick_delivery_fee'] > 0) {
            $charges[] = ['type' => 'quick_delivery', 'label' => 'Quick Delivery Fee', 'amount' => $fees['quick_delivery_fee'], 'is_discount' => false, 'sort_order' => 5];
        }

        $info = null;

        if (CartCoupon::appliesToBucket($cart, $isQuick, $this->carts->couponBucketColumnExists())) {
            $result = $this->coupons->resolve($cart->coupon_code, $cart->user_id, $total);

            if ($result['valid']) {
                $charges[] = ['type' => 'coupon', 'label' => 'Coupon ('.$result['coupon']->code.')', 'amount' => -$result['discount_amount'], 'is_discount' => true, 'sort_order' => 6];
                $info = ['code' => $result['coupon']->code, 'valid' => true, 'discount_amount' => $result['discount_amount']];
            } else {
                $info = ['code' => $cart->coupon_code, 'valid' => false, 'message' => $result['message']];
                $this->carts->setCoupon($cart, null, null);
            }
        }

        return [$charges, $info];
    }
}
