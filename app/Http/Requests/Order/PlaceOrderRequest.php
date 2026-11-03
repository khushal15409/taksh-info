<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\ApiFormRequest;

class PlaceOrderRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'exists:addresses,id'],
            'delivery_type' => ['required', 'string', 'in:30_min,1_day,normal'],
            'payment_method' => ['required', 'in:cod,online'],
        ];
    }
}
