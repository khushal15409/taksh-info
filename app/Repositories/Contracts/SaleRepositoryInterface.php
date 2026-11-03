<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Collection;

interface SaleRepositoryInterface
{
    /** Sales running right now, highest priority first (then newest). */
    public function active(): Collection;

    public function findActive(int $id): ?Sale;

    /** @return int[] */
    public function categoryIds(Sale $sale): array;

    public function includesProduct(Sale $sale, int $productId): bool;

    /** @return int[] */
    public function fulfillmentCenterIds(Sale $sale): array;

    /** Active products of a product-type sale. */
    public function products(Sale $sale): Collection;

    /** Active categories of a category-type sale. */
    public function categories(Sale $sale): Collection;

    /** Active fulfillment centers of an fc / express_30 sale. */
    public function fulfillmentCenters(Sale $sale): Collection;

    /** Id of the nearest active fulfillment center within $radiusKm, if any. */
    public function nearestFulfillmentCenterId(float $latitude, float $longitude, float $radiusKm = 15): ?int;

    public function fulfillmentCenterSupportsExpress(int $fcId): bool;
}
