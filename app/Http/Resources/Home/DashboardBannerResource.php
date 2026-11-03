<?php

namespace App\Http\Resources\Home;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/** A banner inside the dashboard's Banners section. */
class DashboardBannerResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $after = $this->afterSection?->is_active ? $this->afterSection : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'image_url' => $this->image_url,
            'position' => $this->position,
            'type' => $this->type,
            // Middle banners: the (active) section to show below; null = default place.
            'after_section_id' => $after ? $this->after_section_id : null,
            'after_section_name' => $after?->section_name,
            'redirect_type' => $this->redirect_type,
            'redirect_id' => $this->redirect_id,
        ];
    }
}
