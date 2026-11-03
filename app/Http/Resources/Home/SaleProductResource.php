<?php

namespace App\Http\Resources\Home;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/** A product on a sale page. The resource wraps {product, variant, sale}: the sale info is worked out by SaleService. */
class SaleProductResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $variant = $this->variant;
        $sale = $this->sale;
        $original = $variant ? (float) $variant->price : 0;

        return [
            'id' => $this->product->id,
            'name' => $this->product->name,
            'slug' => $this->product->slug,
            'image_url' => $this->product->image,
            'original_price' => $original,
            'sale_price' => $sale ? $sale['sale_price'] : $original,
            'discount_label' => $sale ? $sale['discount_label'] : null,
            'sale_id' => $sale ? $sale['sale_id'] : null,
        ];
    }
}
