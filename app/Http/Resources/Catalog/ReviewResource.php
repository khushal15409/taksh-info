<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class ReviewResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'title' => $this->review_title,
            'review' => $this->review_text,
            'verified' => (bool) $this->is_verified_purchase,
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
