<?php

namespace App\Repositories\Contracts;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Closure;
use Illuminate\Support\Collection;

/** The reads and writes of placing an order. Every method that locks does so for the surrounding transaction. */
interface CheckoutRepositoryInterface
{
    /** Run the callback as one database transaction; an exception rolls everything back. */
    public function transaction(Closure $callback): mixed;

    /** An address, only when it belongs to the customer. */
    public function address(int $userId, int $addressId): ?Address;

    /** The customer's cart row, locked: a second submit of the same checkout waits and then finds the cart consumed. */
    public function lockedCart(int $userId): ?Cart;

    /** The cart's lines with variant and product. */
    public function cartItems(Cart $cart): Collection;

    public function vendor(int $vendorId): ?Vendor;

    /** The vendor's stock rows for these variants, locked in variant id order so two orders cannot wait on each other in a circle. @return Collection<int, VendorProduct> keyed by variant id */
    public function lockedStock(int $vendorId, array $variantIds): Collection;

    public function reserveStock(VendorProduct $row, int $qty): void;

    /** Name and main image of what is ordered, stored on the order line. Empty when those columns are not migrated. */
    public function itemSnapshot(Product $product, ProductVariant $variant): array;

    /** Lock the coupon row so concurrent orders cannot both pass its usage limit. */
    public function lockCoupon(string $code): void;

    public function hasCheckoutGroupColumn(): bool;

    public function createOrder(array $attributes): Order;

    public function createCharge(Order $order, array $attributes): void;

    public function createItem(Order $order, array $attributes): void;

    public function createPayment(array $attributes): Payment;

    /** One use of a coupon per order (a retried request finds the existing record). */
    public function recordCouponUsage(int $couponId, ?int $userId, int $orderId, float $discount): void;

    /** Remove only the lines that were ordered. */
    public function removeCartLines(Cart $cart, array $lineIds): void;

    public function cartHasItems(Cart $cart): bool;

    public function deleteCart(Cart $cart): void;

    /** What the shop has sold since a date (for the income-tax threshold). */
    public function vendorSalesSince(int $vendorId, string $date): float;

    /** The order with the relations a customer response needs. */
    public function withRelations(Order $order): Order;

    public function itemsQty(Order $order): int;

    /** One line of the "orders this checkout created" list. @return array{id: int, order_number: string, vendor_id: ?int, shop_name: ?string, grand_total: mixed, items_count: int} */
    public function summary(Order $order): array;
}
