<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\ApiFormRequest;

class OrderIndexRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'order_status' => ['nullable', 'string', 'max:40'],
            'delivery_mode' => ['nullable', 'string', 'in:quick,standard'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
