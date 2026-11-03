<?php

namespace App\Http\Resources\Catalog;

use App\Helpers\RatingSummary;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/** A product in the catalogue list (wraps {product, variants (already priced rows), reviews, in_stock}). */
class ProductListResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return $this->base() + [
            'variants' => $this->variants,
            'rating_summary' => RatingSummary::for($this->reviews),
            'in_stock' => $this->in_stock,
            'out_of_stock_message' => $this->in_stock ? null : 'Out of stock',
        ];
    }

    /** Fields every product view shares. */
    protected function base(): array
    {
        $p = $this->product;

        return [
            'id' => $p->id,
            'category_id' => $p->category_id,
            'brand_id' => $p->brand_id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'short_description' => $p->short_description,
            'pricing_type' => $p->pricing_type,
            'status' => $p->status,
            'brand' => $p->relationLoaded('brand') && $p->brand ? ['id' => $p->brand->id, 'name' => $p->brand->name] : null,
            'category' => $p->relationLoaded('category') && $p->category ? ['id' => $p->category->id, 'name' => $p->category->name, 'slug' => $p->category->slug, 'parent_id' => $p->category->parent_id] : null,
            'images' => $p->images->map(fn ($img) => [
                'id' => $img->id, 'image_url' => $img->image_url, 'is_primary' => (bool) $img->is_primary, 'sort_order' => $img->sort_order,
            ])->all(),
        ];
    }
}
