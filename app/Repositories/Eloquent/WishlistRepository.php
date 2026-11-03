<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Models\Wishlist;
use App\Repositories\Contracts\WishlistRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WishlistRepository implements WishlistRepositoryInterface
{
    public function paginate(int $userId, int $perPage): LengthAwarePaginator
    {
        return Wishlist::where('user_id', $userId)
            ->whereHas('product')
            ->with(['product' => function ($query) {
                $query->select('id', 'name', 'status', 'taksh_charge', 'vendor_id')
                    ->with([
                        'variants' => fn ($q) => $q->where('status', 'active')->select('id', 'product_id', 'price', 'sale_price', 'customer_price', 'status')->orderBy('price', 'asc'),
                        'images' => fn ($q) => $q->select('id', 'product_id', 'image_url', 'is_primary')->orderBy('is_primary', 'desc')->orderBy('sort_order', 'asc'),
                    ]);
            }])
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function add(int $userId, int $productId): void
    {
        Wishlist::firstOrCreate(['user_id' => $userId, 'product_id' => $productId]);
    }

    public function remove(int $userId, int $productId): bool
    {
        return Wishlist::where('user_id', $userId)->where('product_id', $productId)->delete() > 0;
    }

    public function product(int $productId): ?Product
    {
        return Product::find($productId);
    }

    public function productIds(int $userId): array
    {
        return Wishlist::where('user_id', $userId)->whereHas('product')->pluck('product_id')->map(fn ($id) => (int) $id)->all();
    }
}
