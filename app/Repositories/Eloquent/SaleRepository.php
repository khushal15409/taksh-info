<?php

namespace App\Repositories\Eloquent;

use App\Models\FulfillmentCenter;
use App\Models\Product;
use App\Models\Sale;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Support\Collection;

class SaleRepository implements SaleRepositoryInterface
{
    public function active(): Collection
    {
        return Sale::active()->orderBy('priority', 'desc')->orderBy('created_at', 'desc')->get();
    }

    public function findActive(int $id): ?Sale
    {
        return Sale::active()->find($id);
    }

    public function categoryIds(Sale $sale): array
    {
        return $sale->categories()->pluck('categories.id')->all();
    }

    public function includesProduct(Sale $sale, int $productId): bool
    {
        return $sale->products()->where('products.id', $productId)->exists();
    }

    public function fulfillmentCenterIds(Sale $sale): array
    {
        return $sale->fulfillmentCenters()->pluck('fulfillment_centers.id')->all();
    }

    public function products(Sale $sale): Collection
    {
        return $sale->products()->where('status', 'active')->with(['images', 'variants'])->get();
    }

    public function categories(Sale $sale): Collection
    {
        return $sale->categories()->where('status', 'active')->get();
    }

    public function fulfillmentCenters(Sale $sale): Collection
    {
        return $sale->fulfillmentCenters()->where('status', 'active')->get();
    }

    public function nearestFulfillmentCenterId(float $latitude, float $longitude, float $radiusKm = 15): ?int
    {
        return FulfillmentCenter::nearest($latitude, $longitude, $radiusKm)->first()?->id;
    }

    public function fulfillmentCenterSupportsExpress(int $fcId): bool
    {
        return (bool) FulfillmentCenter::whereKey($fcId)->value('supports_express_30');
    }
}
