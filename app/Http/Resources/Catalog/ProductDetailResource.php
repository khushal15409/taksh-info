<?php

namespace App\Http\Resources\Catalog;

use App\Helpers\RatingSummary;
use Illuminate\Http\Request;

/**
 * The product page. Wraps {product, variants, reviews (approved), latest_reviews, questions, seller, headline, in_stock, return_days}.
 * Adds sourcing details (origin, packer, vendor), the headline price and the latest reviews and Q&A.
 */
class ProductDetailResource extends ProductListResource
{
    public function toArray(Request $request): array
    {
        $p = $this->product;
        $seller = $this->seller;
        $join = fn (array $parts) => ($parts = array_filter(array_map(fn ($x) => trim((string) $x), $parts), fn ($x) => $x !== '')) ? implode(', ', $parts) : null;

        return $this->base() + [
            'net_weight' => $p->net_weight,
            'country_of_origin' => $p->country_of_origin,
            'manufacturer_name' => $p->manufacturer_name,
            'manufacturer_address' => $p->manufacturer_address,
            'manufacturer_pincode' => $p->manufacturer_pincode,
            'packer_name' => $p->packer_name,
            'packer_address' => $p->packer_address,
            'packer_pincode' => $p->packer_pincode,
            'return_days' => $this->return_days,
            'food_type' => $p->food_type ?: 'none',
            'origin' => $p->country_of_origin ?: $p->origin,
            'packaging' => $join([$p->packer_name, $p->packer_address, $p->packer_pincode]),
            'vendor_name' => $seller ? ($seller->shop_name ?: $seller->vendor_name) : null,
            'vendor_address' => $seller ? $join([$seller->shop_address ?: $seller->address, $seller->shop_pincode ?: $seller->pincode]) : null,
            'rating_summary' => RatingSummary::for($this->reviews),
            'reviews' => ReviewResource::collection($this->latest_reviews)->resolve(),
            'questions_answers' => QuestionResource::collection($this->questions)->resolve(),
            'original_price' => $this->headline['original_price'],
            'sale_price' => $this->headline['sale_price'],
            'discount_label' => $this->headline['discount_label'],
            'sale_id' => $this->headline['sale_id'],
            'variants' => $this->variants,
            'in_stock' => $this->in_stock,
            'out_of_stock_message' => $this->in_stock ? null : 'Out of stock',
        ];
    }
}
