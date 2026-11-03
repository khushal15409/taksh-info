<?php

namespace App\Repositories\Eloquent;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Repositories\Contracts\CheckoutRepositoryInterface;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckoutRepository implements CheckoutRepositoryInterface
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function address(int $userId, int $addressId): ?Address
    {
        return Address::where('id', $addressId)->where('user_id', $userId)->first();
    }

    public function lockedCart(int $userId): ?Cart
    {
        return Cart::where('user_id', $userId)->lockForUpdate()->first();
    }

    public function cartItems(Cart $cart): Collection
    {
        return $cart->items()->with(['productVariant.product'])->get();
    }

    public function vendor(int $vendorId): ?Vendor
    {
        return Vendor::find($vendorId);
    }

    public function lockedStock(int $vendorId, array $variantIds): Collection
    {
        return VendorProduct::where('vendor_id', $vendorId)
            ->whereIn('product_variant_id', $variantIds)
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_variant_id');
    }

    public function reserveStock(VendorProduct $row, int $qty): void
    {
        $row->increment('reserved_qty', $qty);
    }

    public function itemSnapshot(Product $product, ProductVariant $variant): array
    {
        static $has = null;
        $has ??= Schema::hasColumn('order_items', 'product_name') && Schema::hasColumn('order_items', 'image_url');

        if (! $has) {
            return [];
        }

        $image = $product->images()->orderByDesc('is_primary')->orderBy('sort_order')->first() ?? $variant->images()->first();

        return ['product_name' => $product->name, 'image_url' => $image?->image_url];
    }

    public function lockCoupon(string $code): void
    {
        \App\Models\Coupon::where('code', strtoupper(trim($code)))->lockForUpdate()->first();
    }

    public function hasCheckoutGroupColumn(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('orders', 'checkout_group_id');
    }

    public function createOrder(array $attributes): Order
    {
        return Order::create($attributes);
    }

    public function createCharge(Order $order, array $attributes): void
    {
        OrderCharge::create(array_merge($attributes, ['order_id' => $order->id]));
    }

    public function createItem(Order $order, array $attributes): void
    {
        $order->items()->create($attributes);
    }

    public function createPayment(array $attributes): Payment
    {
        return Payment::create($attributes);
    }

    public function recordCouponUsage(int $couponId, ?int $userId, int $orderId, float $discount): void
    {
        CouponUsage::firstOrCreate(['coupon_id' => $couponId, 'order_id' => $orderId], ['user_id' => $userId, 'discount_amount' => $discount]);
    }

    public function removeCartLines(Cart $cart, array $lineIds): void
    {
        $cart->items()->whereIn('id', $lineIds)->delete();
    }

    public function cartHasItems(Cart $cart): bool
    {
        return $cart->items()->exists();
    }

    public function deleteCart(Cart $cart): void
    {
        $cart->delete();
    }

    public function vendorSalesSince(int $vendorId, string $date): float
    {
        return (float) DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->where('o.vendor_id', $vendorId)
            ->where('o.created_at', '>=', $date)
            ->sum(DB::raw('i.price * i.qty'));
    }

    public function withRelations(Order $order): Order
    {
        return $order->load([
            'items.productVariant.product:id,name', 'items.productVariant.product.images' => fn ($q) => $q->select('id', 'product_id', 'image_url', 'is_primary')->orderByDesc('is_primary')->orderBy('sort_order'),
            'address.state', 'address.city', 'address.area', 'vendor:id,shop_name,shop_address,shop_pincode', 'charges', 'latestPayment',
        ]);
    }

    public function summary(Order $order): array
    {
        $order->loadMissing('vendor:id,shop_name');

        return [
            'id' => $order->id, 'order_number' => $order->order_number, 'vendor_id' => $order->vendor_id,
            'shop_name' => $order->vendor?->shop_name, 'grand_total' => $order->grand_total, 'items_count' => $this->itemsQty($order),
        ];
    }

    public function itemsQty(Order $order): int
    {
        return (int) $order->items()->sum('qty');
    }
}
