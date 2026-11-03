<?php

namespace App\Http\Requests\Catalog;

class ProductIndexRequest extends CatalogFormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
