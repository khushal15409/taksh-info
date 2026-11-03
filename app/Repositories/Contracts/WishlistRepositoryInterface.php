<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface WishlistRepositoryInterface
{
    /** A customer's wishlist rows (products that still exist), latest first, with each product's active variants and images. */
    public function paginate(int $userId, int $perPage): LengthAwarePaginator;

    /** Saves the product once (saving it again changes nothing). */
    public function add(int $userId, int $productId): void;

    /** Removes the product; false when it was not on the wishlist. */
    public function remove(int $userId, int $productId): bool;

    public function product(int $productId): ?Product;

    /** @return list<int> ids of every product the customer has saved (cheap: used to mark the hearts and count the header). */
    public function productIds(int $userId): array;
}
