<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\ApiFormRequest;

/** Vendor registration (public; a logged-in customer is attached). */
class VendorRequestRequest extends ApiFormRequest
{
    protected int $failureStatus = 400;

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'email' => ['required', 'email', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'business_type' => ['required', 'in:product_vendor,service_vendor'],
        ];
    }
}
