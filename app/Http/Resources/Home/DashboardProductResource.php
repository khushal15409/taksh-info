<?php

namespace App\Http\Resources\Home;

use App\Helpers\RatingSummary;
use App\Http\Resources\ApiResource;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\PricingService;
use Illuminate\Http\Request;

/** A product card inside a dashboard section (price = what the customer pays, original_price = MRP). */
class DashboardProductResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $pricing = app(PricingService::class);
        $variant = $this->variants->first();
        $price = $pricing->finalPrice($this->resource, $variant);
        $original = $pricing->mrp($variant);
        $stock = app(ProductRepositoryInterface::class)->stockInfo($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_url' => $this->images->first()?->image_url,
            'brand' => $this->brand ? ['id' => $this->brand->id, 'name' => $this->brand->name] : null,
            'category' => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null,
            'price' => $price,
            'original_price' => $original,
            // percentage printed as "<n>% OFF": MRP vs what the customer pays
            'discount' => $variant ? $pricing->discountPercent($original, $price) : 0,
            'rating_summary' => RatingSummary::for($this->reviews),
            'in_stock' => $stock['in_stock'],
            'out_of_stock_message' => $stock['out_of_stock_message'],
        ];
    }
}
