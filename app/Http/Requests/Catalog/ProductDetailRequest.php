<?php

namespace App\Http\Requests\Catalog;

class ProductDetailRequest extends CatalogFormRequest
{
    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
