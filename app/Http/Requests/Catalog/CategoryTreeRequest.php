<?php

namespace App\Http\Requests\Catalog;

class CategoryTreeRequest extends CatalogFormRequest
{
    public function rules(): array
    {
        // a string ("standard_delivery", "all", "a,b") or a list
        return ['delivery_type' => ['nullable']];
    }
}
