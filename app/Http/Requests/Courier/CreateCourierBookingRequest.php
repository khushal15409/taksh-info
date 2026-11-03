<?php

namespace App\Http\Requests\Courier;

use App\Http\Requests\ApiFormRequest;

class CreateCourierBookingRequest extends ApiFormRequest
{
    protected int $failureStatus = 400;

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'pickup_address' => ['required', 'string', 'max:2000'],
            'pickup_pincode' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'delivery_name' => ['required', 'string', 'max:255'],
            'delivery_mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'delivery_address' => ['required', 'string', 'max:2000'],
            'delivery_pincode' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'weight_grams' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'dimension_unit' => ['required', 'in:cm,inch'],
            'length' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'width' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'height' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'product_details' => ['nullable', 'string', 'max:2000'],
            'packaging_details' => ['nullable', 'string', 'max:2000'],
            'item_photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
            // normally neither is sent: the server asks Shiprocket and books the cheapest courier
            'courier_delivery_partner_id' => ['nullable', 'exists:service_courier_delivery_partners,id'],
            'shiprocket_courier_company_id' => ['nullable', 'string', 'max:64', 'prohibits:courier_delivery_partner_id'],
            // display only: the rate the customer saw. Detects a stale quote, never used as a price.
            'quoted_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
