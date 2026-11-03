<?php

namespace App\Services;

use App\Http\Resources\Home\SaleProductResource;
use App\Http\Resources\Home\SaleResource;
use App\Models\Sale;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Support\Collection;

/** The sale pages' data: the sale, and the products/categories/centers it covers (taksh-admin SaleController). */
class SaleCatalogService
{
    public function __construct(
        private readonly SaleRepositoryInterface $sales,
        private readonly ProductRepositoryInterface $products,
        private readonly SaleService $saleService,
    ) {}

    /** Nearest fulfillment center (15 km) for a customer location, if one was given. */
    public function fulfillmentCenterFor(?float $latitude, ?float $longitude): ?int
    {
        return $latitude && $longitude ? $this->sales->nearestFulfillmentCenterId($latitude, $longitude, 15) : null;
    }

    public function list(?int $fcId): array
    {
        return SaleResource::collection($this->saleService->getActiveSales($fcId))->resolve();
    }

    public function find(int $id): ?Sale
    {
        return $this->sales->findActive($id);
    }

    public function details(Sale $sale, ?int $fcId): array
    {
        $data = SaleResource::make($sale)->resolve();

        if ($sale->sale_type === 'product') {
            $data['products'] = $this->productRows($this->sales->products($sale), $fcId);
        } elseif ($sale->sale_type === 'category') {
            $data['categories'] = $this->sales->categories($sale)->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'image_url' => $c->image_url,
            ])->all();
        } elseif (in_array($sale->sale_type, ['fc', 'express_30'], true)) {
            $data['fulfillment_centers'] = $this->sales->fulfillmentCenters($sale)->map(fn ($fc) => [
                'id' => $fc->id, 'name' => $fc->name, 'latitude' => (float) $fc->latitude, 'longitude' => (float) $fc->longitude,
            ])->all();
        }

        return $data;
    }

    public function productsOf(Sale $sale, ?int $fcId): array
    {
        $products = collect();

        switch ($sale->sale_type) {
            case 'product':
                $products = $this->sales->products($sale);
                break;
            case 'category':
                $products = $this->products->activeInCategories($this->sales->categoryIds($sale));
                break;
            case 'global':
                $products = $this->products->activeLimited(50);
                break;
            case 'fc':
                if ($fcId && in_array($fcId, $this->sales->fulfillmentCenterIds($sale))) {
                    $products = $this->products->activeForFulfillmentCenter($fcId);
                }
                break;
            case 'express_30':
                $centers = $this->sales->fulfillmentCenterIds($sale);
                if ($fcId && ($centers === [] || in_array($fcId, $centers))) {
                    $products = $this->products->activeForFulfillmentCenter($fcId, true);
                }
                break;
        }

        return $this->productRows($products, $fcId);
    }

    private function productRows(Collection $products, ?int $fcId): array
    {
        return $products->map(fn ($product) => SaleProductResource::make((object) [
            'product' => $product,
            'variant' => $this->products->cheapestActiveVariant($product),
            'sale' => $this->saleService->applySale($product, $fcId),
        ])->resolve())->all();
    }
}
