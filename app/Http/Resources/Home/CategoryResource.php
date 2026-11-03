<?php

namespace App\Http\Resources\Home;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class CategoryResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'image_url' => $this->image_url,
            'icon_url' => $this->icon_url,
            // only present when the query counted the products (the parent-category strip)
            'products_count' => $this->when(array_key_exists('products_count', $this->resource->getAttributes()), fn () => $this->products_count ?? 0),
        ];
    }
}
