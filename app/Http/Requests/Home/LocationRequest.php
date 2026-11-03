<?php

namespace App\Http\Requests\Home;

use App\Http\Requests\ApiFormRequest;

/** Optional customer coordinates; with them the nearest fulfillment center (15 km) scopes a sale. */
class LocationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
