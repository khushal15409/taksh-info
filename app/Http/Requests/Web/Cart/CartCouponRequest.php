<?php

namespace App\Http\Requests\Web\Cart;

use App\Http\Requests\Web\WebFormRequest;

class CartCouponRequest extends WebFormRequest
{
    public function rules(): array
    {
        return ['coupon_code' => ['required', 'string', 'max:50'], 'type' => ['nullable', 'in:normal,30_min']];
    }

    public function messages(): array
    {
        return ['coupon_code.required' => 'Enter a coupon code'];
    }

    public function type(): string
    {
        return $this->input('type', 'normal');
    }
}
