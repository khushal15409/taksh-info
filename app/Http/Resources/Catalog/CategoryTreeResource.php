<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class CategoryTreeResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'image_url' => $this->image_url,
            'icon_url' => $this->icon_url,
            'delivery_types' => $this->delivery_types,
            'children' => $this->whenLoaded('children', fn () => self::collection($this->children)->resolve()),
        ];
    }
}
