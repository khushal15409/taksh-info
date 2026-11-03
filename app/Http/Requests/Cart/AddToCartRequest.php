<?php

namespace App\Http\Requests\Cart;

class AddToCartRequest extends CartFormRequest
{
    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'delivery_type' => ['nullable', 'string'],
            'address_id' => ['nullable', 'integer'],
        ];
    }
}
