<?php

namespace App\Http\Resources\Home;

use App\Http\Resources\ApiResource;
use App\Services\PricingService;
use Illuminate\Http\Request;

/** A recently viewed product: cheapest payable price and a thumbnail (primary image, else the first). */
class RecentViewResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $pricing = app(PricingService::class);

        $price = $product->variants
            ->map(fn ($variant) => $pricing->finalPrice($product, $variant))
            ->filter(fn ($amount) => $amount > 0)
            ->min() ?? 0;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $price,
            'thumbnail' => ($product->images->where('is_primary', true)->first() ?? $product->images->first())?->image_url,
        ];
    }
}
