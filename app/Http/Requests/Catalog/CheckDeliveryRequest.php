<?php

namespace App\Http\Requests\Catalog;

class CheckDeliveryRequest extends CatalogFormRequest
{
    public function rules(): array
    {
        return [
            'pincode' => ['required', 'string', 'max:10'],
            'product_variant_id' => ['nullable', 'integer'],
            'qty' => ['nullable', 'integer', 'min:1'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ];
    }
}
