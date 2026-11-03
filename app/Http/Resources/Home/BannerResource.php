<?php

namespace App\Http\Resources\Home;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/** A banner as GET /ecommerce/banners returns it. */
class BannerResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'redirect_type' => $this->redirect_type,
            'redirect_id' => $this->redirect_id,
            'redirect_url' => $this->redirect_url,
            'position' => $this->position,
            'after_section_id' => $this->after_section_id,
            'start_date' => $this->start_date?->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'sort_order' => $this->sort_order,
        ];
    }
}
