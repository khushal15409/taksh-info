<?php

namespace App\Http\Requests\Cart;

class CartIndexRequest extends CartFormRequest
{
    public function rules(): array
    {
        return ['delivery_type' => ['required', 'string', 'exists:delivery_types,code']];
    }
}
