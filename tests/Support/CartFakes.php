<?php

namespace Tests\Support;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\DeliveryType;
use App\Models\ProductVariant;
use App\Repositories\Contracts\CartCatalogRepositoryInterface;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Support\DeliveryBucket;
use Closure;
use Illuminate\Support\Collection;

/** An in-memory cart store with the same ownership and quantity rules as the Eloquent repository. */
class FakeCartRepository implements CartRepositoryInterface
{
    /** @var array<int, Cart> */
    public array $carts = [];

    /** @var array<int, array<int, CartItem>> cartId => itemId => item */
    public array $lines = [];

    public array $merged = [];

    public bool $bucketColumn = true;

    private int $nextCart = 1;

    private int $nextItem = 1;

    public function mergeGuestCartIntoUser(int $userId, string $guestToken): void
    {
        $this->merged[] = [$userId, $guestToken];
    }

    public function forUser(int $userId): ?Cart
    {
        return collect($this->carts)->first(fn ($c) => $c->user_id === $userId);
    }

    public function forGuest(string $guestToken): ?Cart
    {
        return collect($this->carts)->first(fn ($c) => $c->user_id === null && $c->guest_token === $guestToken);
    }

    private function make(?int $userId, ?string $token): Cart
    {
        $cart = (new Cart)->forceFill(['id' => $this->nextCart++, 'user_id' => $userId, 'guest_token' => $token, 'coupon_code' => null, 'coupon_delivery_type' => null]);
        $this->lines[$cart->id] = [];

        return $this->carts[$cart->id] = $cart;
    }

    public function firstOrCreateForUser(int $userId): Cart
    {
        return $this->forUser($userId) ?? $this->make($userId, null);
    }

    public function firstOrCreateForGuest(string $guestToken): Cart
    {
        return $this->forGuest($guestToken) ?? $this->make(null, $guestToken);
    }

    public function addLine(Cart $cart, ProductVariant $variant, int $qty, Closure $check): CartItem
    {
        $existing = collect($this->lines[$cart->id])->first(fn ($i) => $i->product_variant_id === $variant->id);
        $newQty = (int) ($existing?->qty ?? 0) + $qty;

        $check($newQty);

        if ($existing) {
            $existing->qty = $newQty;

            return $existing;
        }

        $item = (new CartItem)->forceFill(['id' => $this->nextItem++, 'cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'qty' => $newQty]);
        $item->setRelation('productVariant', $variant)->setRelation('cart', $cart);

        return $this->lines[$cart->id][$item->id] = $item;
    }

    public function findOwnedItem(int $itemId, ?int $userId, ?string $guestToken): ?CartItem
    {
        foreach ($this->lines as $cartId => $items) {
            if (! isset($items[$itemId])) {
                continue;
            }
            $cart = $this->carts[$cartId];

            if ($userId !== null) {
                return $cart->user_id === $userId ? $items[$itemId] : null;
            }

            return $guestToken && $cart->user_id === null && $cart->guest_token === $guestToken ? $items[$itemId] : null;
        }

        return null;
    }

    public function setLineQty(CartItem $item, int $qty, Closure $check): ?CartItem
    {
        if (! isset($this->lines[$item->cart_id][$item->id])) {
            return null;
        }

        if ($qty > $item->qty) {
            $check($item);
        }
        $item->qty = $qty;

        return $item;
    }

    public function deleteItem(CartItem $item): void
    {
        unset($this->lines[$item->cart_id][$item->id]);
    }

    public function items(Cart $cart, ?string $deliveryType = null): Collection
    {
        return collect($this->lines[$cart->id])->filter(function ($item) use ($deliveryType) {
            return ! DeliveryBucket::given($deliveryType)
                || (bool) $item->productVariant->product->is_express_30 === DeliveryBucket::isQuick($deliveryType);
        })->values();
    }

    public function clear(Cart $cart, ?string $deliveryType = null): void
    {
        foreach ($this->items($cart, $deliveryType) as $item) {
            unset($this->lines[$cart->id][$item->id]);
        }
    }

    public function hasItems(Cart $cart): bool
    {
        return $this->lines[$cart->id] !== [];
    }

    public function setCoupon(Cart $cart, ?string $code, ?string $bucket): void
    {
        $cart->coupon_code = $code;
        $cart->coupon_delivery_type = $bucket;
    }

    public function couponBucketColumnExists(): bool
    {
        return $this->bucketColumn;
    }
}

class FakeCartCatalogRepository implements CartCatalogRepositoryInterface
{
    /** @var array<int, ProductVariant> */
    public array $variants = [];

    /** @var array<string, int> "vendor_variant" => free stock */
    public array $free = [];

    public array $approved = [7];

    public array $names = [7 => 'Fresh Mart'];

    public array $types = ['normal' => 40, '30_min' => 30];

    public function variant(int $id): ?ProductVariant
    {
        return $this->variants[$id] ?? null;
    }

    public function availableStock(int $vendorId, int $variantId): int
    {
        return $this->free[$vendorId.'_'.$variantId] ?? 0;
    }

    public function vendorApproved(int $vendorId): bool
    {
        return in_array($vendorId, $this->approved);
    }

    public function approvedVendorIds(array $vendorIds): Collection
    {
        return collect($vendorIds)->filter(fn ($id) => in_array($id, $this->approved))->values();
    }

    public function vendorNames(array $vendorIds): Collection
    {
        return collect($this->names);
    }

    public function deliveryType(string $code): ?DeliveryType
    {
        return isset($this->types[$code]) ? (new DeliveryType)->forceFill(['code' => $code, 'estimated_minutes' => $this->types[$code]]) : null;
    }
}

class FakeCouponRepository implements CouponRepositoryInterface
{
    /** @var array<string, Coupon> */
    public array $coupons = [];

    public int $used = 0;

    public int $usedByUser = 0;

    public function findByCode(string $code): ?Coupon
    {
        return $this->coupons[$code] ?? null;
    }

    public function usageCount(Coupon $coupon): int
    {
        return $this->used;
    }

    public function userUsageCount(Coupon $coupon, int $userId): int
    {
        return $this->usedByUser;
    }

    public static function coupon(array $attrs = []): Coupon
    {
        return (new Coupon)->forceFill($attrs + [
            'id' => 1, 'code' => 'SAVE50', 'is_active' => true, 'discount_type' => 'flat', 'discount_value' => 50,
            'max_discount_amount' => null, 'min_cart_value' => 0, 'usage_limit' => null, 'usage_limit_per_user' => null, 'start_at' => null, 'end_at' => null,
        ]);
    }
}
