<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\ApiFormRequest;

class CreatePaymentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['order_id' => ['required', 'exists:orders,id']];
    }
}
