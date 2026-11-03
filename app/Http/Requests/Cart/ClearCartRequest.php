<?php

namespace App\Http\Requests\Cart;

class ClearCartRequest extends CartFormRequest
{
    public function rules(): array
    {
        return ['delivery_type' => ['nullable', 'string', 'exists:delivery_types,code']];
    }
}
