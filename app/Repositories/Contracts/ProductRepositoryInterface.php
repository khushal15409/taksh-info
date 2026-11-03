<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface
{
    /** @return array{in_stock: bool, out_of_stock_message: ?string} */
    public function stockInfo(Product $product): array;

    /** Cheapest active variant. */
    public function cheapestActiveVariant(Product $product): ?ProductVariant;

    /** Active products of the given categories (with images and variants). */
    public function activeInCategories(array $categoryIds): Collection;

    /** Up to $limit active products. */
    public function activeLimited(int $limit): Collection;

    /** Active products stocked at a fulfillment center; `$quickOnly` keeps only active 30-minute items. */
    public function activeForFulfillmentCenter(int $fcId, bool $quickOnly = false): Collection;
}
