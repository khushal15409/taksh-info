<?php

namespace App\Http\Requests\Catalog;

class SubCategoryRequest extends CatalogFormRequest
{
    public function rules(): array
    {
        return [
            'parent_id' => ['required', 'integer', 'exists:categories,id'],
            'delivery_type' => ['nullable', 'string', 'max:30'],
        ];
    }
}
