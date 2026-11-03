<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ProductSearchRepositoryInterface
{
    /**
     * Which products match (not their prices): FULLTEXT on name/keywords, else LIKE, plus brand and category names.
     *
     * @return array<int, array{id: int, brand_name: ?string}>
     */
    public function match(string $keyword, ?int $vendorId, int $limit, ?string $deliveryType): array;

    /** Products with active variants (cheapest first) and images (primary first), keyed by id. */
    public function productsWithRelations(array $productIds): Collection;

    /** @return Collection<int, object{average: float, total: int}> per product id */
    public function ratings(array $productIds): Collection;

    /** Does the product's vendor still have free stock on one of the given variants? */
    public function vendorHasFreeStock(int $vendorId, array $variantIds): bool;
}
