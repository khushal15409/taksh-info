<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\ApiFormRequest;

class CreateReturnRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:orders,id'],
            'order_item_id' => ['required', 'exists:order_items,id'],
            // how many pieces of that line come back; checked against the line in the service, which is the only place that knows it
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            // nothing downstream can refund or replace without knowing which
            'resolution' => ['required', 'in:refund,replacement,wallet'],
        ];
    }
}
