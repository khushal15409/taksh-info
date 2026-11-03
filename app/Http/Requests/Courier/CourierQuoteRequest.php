<?php

namespace App\Http\Requests\Courier;

use App\Http\Requests\ApiFormRequest;

class CourierQuoteRequest extends ApiFormRequest
{
    protected int $failureStatus = 400;

    public function rules(): array
    {
        return [
            // both pincodes -> live Shiprocket rates; neither -> the legacy local partners
            'pickup_pincode' => ['nullable', 'required_with:delivery_pincode', 'digits:6'],
            'delivery_pincode' => ['nullable', 'required_with:pickup_pincode', 'digits:6'],
            'weight_grams' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'dimension_unit' => ['required', 'in:cm,inch'],
            'length' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'width' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'height' => ['required', 'numeric', 'min:0.01', 'max:999999'],
        ];
    }
}
