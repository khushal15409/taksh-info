<?php

namespace App\Http\Requests\Cart;

class UpdateCartItemRequest extends CartFormRequest
{
    public function rules(): array
    {
        return [
            'cart_item_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1'],
            'delivery_type' => ['nullable', 'string'],
        ];
    }
}
