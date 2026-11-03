<?php

namespace App\Http\Requests\Cart;

class RemoveCouponRequest extends CartFormRequest
{
    public function rules(): array
    {
        return ['delivery_type' => ['nullable', 'string']];
    }
}
