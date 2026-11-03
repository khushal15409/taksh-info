<?php

namespace App\Http\Requests\Courier;

use App\Http\Requests\ApiFormRequest;

/** payment/create needs the booking; payment/verify also the Razorpay ids. */
class CourierPaymentRequest extends ApiFormRequest
{
    protected int $failureStatus = 400;

    public function rules(): array
    {
        $rules = ['courier_booking_id' => ['required', 'integer']];

        if ($this->routeIs('service.courier.payment.verify')) {
            $rules += [
                'razorpay_order_id' => ['required', 'string', 'max:100'],
                'razorpay_payment_id' => ['required', 'string', 'max:100'],
                'razorpay_signature' => ['required', 'string', 'max:200'],
            ];
        }

        return $rules;
    }
}
