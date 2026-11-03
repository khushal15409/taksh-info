<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Collection;

class ProductRepository implements ProductRepositoryInterface
{
    public function stockInfo(Product $product): array
    {
        $vendorId = $product->vendor_id;

        $inStock = $vendorId
            ? $product->variants()->where('status', 'active')
                ->whereHas('vendorProducts', fn ($q) => $q->where('vendor_id', $vendorId)->whereRaw('(stock_qty - reserved_qty) > 0'))
                ->exists()
            : $product->variants()->where('status', 'active')->exists();

        return ['in_stock' => $inStock, 'out_of_stock_message' => $inStock ? null : 'Out of stock'];
    }

    public function cheapestActiveVariant(Product $product): ?ProductVariant
    {
        return $product->variants()->where('status', 'active')->orderBy('price', 'asc')->first();
    }

    public function activeInCategories(array $categoryIds): Collection
    {
        return Product::with(['images', 'variants'])->where('status', 'active')->whereIn('category_id', $categoryIds)->get();
    }

    public function activeLimited(int $limit): Collection
    {
        return Product::with(['images', 'variants'])->where('status', 'active')->limit($limit)->get();
    }

    public function activeForFulfillmentCenter(int $fcId, bool $quickOnly = false): Collection
    {
        return Product::with(['images', 'variants'])
            ->where('status', 'active')
            ->where('fulfillment_center_id', $fcId)
            ->when($quickOnly, fn ($q) => $q->where('is_express_30', true)->where('express_30_status', 'active'))
            ->get();
    }
}
