<?php

namespace App\Repositories\Contracts;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Closure;
use Illuminate\Support\Collection;

interface CartRepositoryInterface
{
    /** Move the guest cart's items into the user's cart (quantities add up) and remove the guest cart. */
    public function mergeGuestCartIntoUser(int $userId, string $guestToken): void;

    public function forUser(int $userId): ?Cart;

    /** A guest-owned cart only (never a customer's). */
    public function forGuest(string $guestToken): ?Cart;

    public function firstOrCreateForUser(int $userId): Cart;

    public function firstOrCreateForGuest(string $guestToken): Cart;

    /**
     * Add to a line under a lock on the cart, so a double tap or two devices cannot create duplicate lines or skip the stock check.
     * Adding what is already there ADDS to its quantity. `$check(int $newQty)` may throw to refuse the line.
     */
    public function addLine(Cart $cart, ProductVariant $variant, int $qty, Closure $check): CartItem;

    /** The item, but only when the caller owns its cart; a missing item and someone else's look the same. */
    public function findOwnedItem(int $itemId, ?int $userId, ?string $guestToken): ?CartItem;

    /** Set a line's quantity under a lock; `$check(int $current)` runs when the quantity goes up. Null when the line is gone. */
    public function setLineQty(CartItem $item, int $qty, Closure $check): ?CartItem;

    public function deleteItem(CartItem $item): void;

    /** Lines of the cart (with variant, product and images), optionally only one bucket ('30_min' = quick, any other code = standard). */
    public function items(Cart $cart, ?string $deliveryType = null): Collection;

    public function clear(Cart $cart, ?string $deliveryType = null): void;

    public function hasItems(Cart $cart): bool;

    public function setCoupon(Cart $cart, ?string $code, ?string $bucket): void;

    /** Is carts.coupon_delivery_type migrated on this database? */
    public function couponBucketColumnExists(): bool;
}
