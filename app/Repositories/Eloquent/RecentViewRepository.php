<?php

namespace App\Repositories\Eloquent;

use App\Models\Category;
use App\Models\RecentView;
use App\Repositories\Contracts\RecentViewRepositoryInterface;
use Illuminate\Support\Collection;

class RecentViewRepository implements RecentViewRepositoryInterface
{
    public function forUser(int $userId, ?string $deliveryType = null): Collection
    {
        return RecentView::where('user_id', $userId)
            ->when($deliveryType, fn ($q) => $q->whereHas('product', function ($p) use ($deliveryType) {
                $p->whereIn('category_id', Category::forDeliveryType($deliveryType)->select('categories.id'));

                if ($deliveryType === Category::DELIVERY_EXPRESS) {
                    $p->where('is_express_30', true)->where('express_30_status', 'active');
                }
            }))
            ->with(['product' => function ($query) {
                $query->select('id', 'name', 'taksh_charge')
                    ->with([
                        'variants' => fn ($q) => $q->where('status', 'active')->select('id', 'product_id', 'price', 'sale_price', 'customer_price')->orderBy('price', 'asc'),
                        'images' => fn ($q) => $q->select('id', 'product_id', 'image_url', 'is_primary')->orderBy('is_primary', 'desc')->orderBy('sort_order', 'asc'),
                    ]);
            }])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function record(int $userId, int $productId): void
    {
        RecentView::where('user_id', $userId)->where('product_id', $productId)->delete();
        RecentView::create(['user_id' => $userId, 'product_id' => $productId, 'created_at' => now()]);

        $stale = RecentView::where('user_id', $userId)->orderBy('created_at', 'desc')->orderBy('id', 'desc')->skip(20)->take(PHP_INT_MAX)->pluck('id');
        if ($stale->isNotEmpty()) {
            RecentView::whereIn('id', $stale)->delete();
        }
    }
}
