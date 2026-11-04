<?php

namespace App\Repositories\Eloquent;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Support\DeliveryBucket;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use App\Repositories\Contracts\CartRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CartRepository implements CartRepositoryInterface
{
    public function mergeGuestCartIntoUser(int $userId, string $guestToken): void
    {
        $guestCart = Cart::where('guest_token', $guestToken)->whereNull('user_id')->first();

        if (! $guestCart) {
            return;
        }

        DB::transaction(function () use ($guestCart, $userId) {
            $userCart = Cart::firstOrCreate(['user_id' => $userId]);

            foreach ($guestCart->items as $guestItem) {
                $existing = $userCart->items()->where('product_variant_id', $guestItem->product_variant_id)->first();

                if ($existing) {
                    $existing->update(['qty' => $existing->qty + $guestItem->qty]);
                } else {
                    $userCart->items()->create([
                        'product_variant_id' => $guestItem->product_variant_id,
                        'qty' => $guestItem->qty,
                    ]);
                }
            }

            $guestCart->delete();
        });
    }

    public function forUser(int $userId): ?Cart
    {
        return Cart::where('user_id', $userId)->first();
    }

    public function forGuest(string $guestToken): ?Cart
    {
        return Cart::where('guest_token', $guestToken)->whereNull('user_id')->first();
    }

    public function firstOrCreateForUser(int $userId): Cart
    {
        try {
            return Cart::firstOrCreate(['user_id' => $userId]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // a parallel first add created the cart a moment earlier (unique carts.user_id)
            return Cart::where('user_id', $userId)->firstOrFail();
        }
    }

    public function firstOrCreateForGuest(string $guestToken): Cart
    {
        // a guest token only ever addresses a guest-owned cart; it never resolves to a customer's
        return Cart::firstOrCreate(['guest_token' => $guestToken, 'user_id' => null]);
    }

    public function addLine(Cart $cart, ProductVariant $variant, int $qty, Closure $check): CartItem
    {
        return DB::transaction(function () use ($cart, $variant, $qty, $check) {
            Cart::whereKey($cart->id)->lockForUpdate()->first();

            $existing = CartItem::where('cart_id', $cart->id)->where('product_variant_id', $variant->id)->first();
            $newQty = (int) ($existing?->qty ?? 0) + $qty;

            $check($newQty);

            return CartItem::updateOrCreate(['cart_id' => $cart->id, 'product_variant_id' => $variant->id], ['qty' => $newQty]);
        });
    }

    public function findOwnedItem(int $itemId, ?int $userId, ?string $guestToken): ?CartItem
    {
        $item = CartItem::with('cart')->find($itemId);
        $cart = $item?->cart;

        if (! $cart) {
            return null;
        }

        if ($userId !== null) {
            return $cart->user_id !== null && (int) $cart->user_id === $userId ? $item : null;
        }

        // a missing token never matches (user-owned carts have a NULL token)
        if ($guestToken === null || $guestToken === '' || $cart->user_id !== null || $cart->guest_token === null) {
            return null;
        }

        return hash_equals((string) $cart->guest_token, $guestToken) ? $item : null;
    }

    public function setLineQty(CartItem $item, int $qty, Closure $check): ?CartItem
    {
        return DB::transaction(function () use ($item, $qty, $check) {
            $locked = CartItem::whereKey($item->id)->lockForUpdate()->first();

            if (! $locked) {
                return null;
            }

            // lowering a quantity is always allowed; raising it is checked against availability and stock
            if ($qty > (int) $locked->qty) {
                $check($locked);
            }

            $locked->update(['qty' => $qty]);

            return $locked;
        });
    }

    public function deleteItem(CartItem $item): void
    {
        $item->delete();
    }

    public function items(Cart $cart, ?string $deliveryType = null): Collection
    {
        return $this->bucket($cart->items()->with(['productVariant.product.images', 'productVariant.images']), $deliveryType)->get();
    }

    public function clear(Cart $cart, ?string $deliveryType = null): void
    {
        $this->bucket($cart->items(), $deliveryType)->delete();
    }

    public function hasItems(Cart $cart): bool
    {
        return $cart->items()->exists();
    }

    public function setCoupon(Cart $cart, ?string $code, ?string $bucket): void
    {
        $cart->coupon_code = $code;
        if ($this->couponBucketColumnExists()) {
            $cart->coupon_delivery_type = $bucket;
        }
        $cart->save();
    }

    public function couponBucketColumnExists(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('carts', 'coupon_delivery_type');
    }

    /** '30_min' keeps quick products only, any other code keeps standard products only, none keeps everything. */
    private function bucket($query, ?string $deliveryType)
    {
        if (DeliveryBucket::given($deliveryType)) {
            $quick = DeliveryBucket::isQuick($deliveryType);
            $query->whereHas('productVariant.product', fn ($q) => $q->where('is_express_30', $quick));
        }

        return $query;
    }
}
