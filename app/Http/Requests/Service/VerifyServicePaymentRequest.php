<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\ApiFormRequest;

class VerifyServicePaymentRequest extends ApiFormRequest
{
    protected int $failureStatus = 400;

    public function rules(): array
    {
        return [
            'razorpay_order_id' => ['required', 'string', 'max:100'],
            'razorpay_payment_id' => ['required', 'string', 'max:100'],
            'razorpay_signature' => ['required', 'string', 'max:200'],
        ];
    }
}
