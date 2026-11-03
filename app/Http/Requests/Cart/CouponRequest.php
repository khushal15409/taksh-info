<?php

namespace App\Http\Requests\Cart;

class CouponRequest extends CartFormRequest
{
    public function rules(): array
    {
        return [
            'coupon_code' => ['required', 'string', 'max:64'],
            'delivery_type' => ['nullable', 'string', 'exists:delivery_types,code'],
        ];
    }
}
