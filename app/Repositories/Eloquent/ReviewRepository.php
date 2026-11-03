<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Support\Collection;

class ReviewRepository implements ReviewRepositoryInterface
{
    public function productExists(int $productId): bool
    {
        return Product::whereKey($productId)->exists();
    }

    public function hasDelivered(int $userId, int $productId): bool
    {
        return Order::where('user_id', $userId)
            ->where('order_status', 'delivered')
            ->whereHas('items.productVariant', fn ($q) => $q->where('product_id', $productId))
            ->exists();
    }

    public function findByUser(int $userId, int $productId): ?ProductReview
    {
        return ProductReview::where('user_id', $userId)->where('product_id', $productId)->first();
    }

    public function save(int $userId, int $productId, array $attributes): ProductReview
    {
        return ProductReview::updateOrCreate(['user_id' => $userId, 'product_id' => $productId], $attributes);
    }

    public function summary(int $productId): array
    {
        $approved = ProductReview::where('product_id', $productId)->where('is_approved', true);

        return [
            'average_rating' => round((float) (clone $approved)->avg('rating'), 1),
            'total_reviews' => (clone $approved)->count(),
        ];
    }

    public function pendingRows(int $userId, int $withinDays, int $limit): Collection
    {
        $reviewed = ProductReview::where('user_id', $userId)->pluck('product_id');

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->where('orders.user_id', $userId)
            ->where('orders.order_status', 'delivered')
            ->whereRaw('COALESCE(orders.delivered_at, orders.updated_at) >= ?', [now()->subDays($withinDays)])
            ->whereNotIn('product_variants.product_id', $reviewed)
            ->orderByRaw('COALESCE(orders.delivered_at, orders.updated_at) DESC')
            ->orderByDesc('orders.id')
            ->get([
                'orders.id as order_id', 'orders.order_number', 'orders.delivered_at',
                'product_variants.product_id', 'order_items.product_name', 'order_items.image_url',
            ])
            ->unique('product_id')
            ->take($limit)
            ->values();
    }

    public function productsWithImages(array $productIds): Collection
    {
        return Product::with(['images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')])
            ->whereIn('id', $productIds)->get()->keyBy('id');
    }
}
