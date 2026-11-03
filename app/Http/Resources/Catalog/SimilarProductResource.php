<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use App\Services\PricingService;
use Illuminate\Http\Request;

class SimilarProductResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $pricing = app(PricingService::class);
        $variant = $this->variants->first();               // cheapest active variant
        $reviews = $this->reviews;
        $total = $reviews->count();

        return [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'price' => $pricing->mrp($variant),
            'discount_price' => $variant && $variant->sale_price ? $pricing->finalPrice($this->resource, $variant) : null,
            'primary_image' => $this->images->first()?->image_url,
            'rating' => $total > 0 ? round($reviews->avg('rating'), 1) : 0,
            'total_reviews' => $total,
            'stock_status' => $this->variants->count() > 0 ? 'in_stock' : 'out_of_stock',
        ];
    }
}
