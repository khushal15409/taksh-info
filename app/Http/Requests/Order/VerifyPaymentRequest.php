<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\ApiFormRequest;

class VerifyPaymentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ];
    }
}
