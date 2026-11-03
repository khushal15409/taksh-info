<?php

namespace App\Http\Resources\Service;

use App\Http\Resources\ApiResource;
use App\Services\MediaUrlService;
use Illuminate\Http\Request;

/** A service as the customer sees it. The price is the admin's service_charge (base_price kept for older clients). */
class ServiceResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $media = app(MediaUrlService::class);
        $charge = (float) $this->effective_charge;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'service_charge' => $charge,
            'base_price' => $charge,
            'duration' => $this->duration,
            'image' => $media->url($this->image),
            'images' => collect($this->images ?? [])->map(fn ($i) => $media->url($i))->values()->all(),
        ];
    }
}
