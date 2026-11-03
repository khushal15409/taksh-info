<?php

namespace App\Http\Requests\Catalog;

class ProductSearchRequest extends CatalogFormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['required', 'string', 'min:1', 'max:255'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            // '30_min' = quick products only, any other code = standard only; omitted = no filtering
            'delivery_type' => ['nullable', 'string', 'max:32'],
        ];
    }
}
