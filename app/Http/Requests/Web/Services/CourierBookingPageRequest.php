<?php

namespace App\Http\Requests\Web\Services;

use App\Helpers\Phone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The courier booking form. Which courier is booked is decided on the server (the cheapest, unless the rate lookup picked one); the price
 * is never taken from the form - `quoted_rate` only detects a quote that went stale. Errors come back under the fields.
 */
class CourierBookingPageRequest extends FormRequest
{
    protected $errorBag = 'courier';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['customer_mobile', 'delivery_mobile'] as $f) {
            if ($this->has($f)) {
                $this->merge([$f => Phone::normalize($this->input($f)) ?? $this->input($f)]);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return route('services').'#courier';
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'], 'customer_mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'pickup_address' => ['required', 'string', 'max:2000'], 'pickup_pincode' => ['required', 'digits:6'],
            'delivery_name' => ['required', 'string', 'max:255'], 'delivery_mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'delivery_address' => ['required', 'string', 'max:2000'], 'delivery_pincode' => ['required', 'digits:6'],
            'weight_grams' => ['required', 'numeric', 'min:1', 'max:999999999'], 'dimension_unit' => ['required', 'in:cm,inch'],
            'length' => ['required', 'numeric', 'min:0.01', 'max:999999'], 'width' => ['required', 'numeric', 'min:0.01', 'max:999999'], 'height' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'customer_notes' => ['nullable', 'string', 'max:2000'], 'product_details' => ['nullable', 'string', 'max:2000'],
            'item_photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
            'shiprocket_courier_company_id' => ['nullable', 'string', 'max:64'], 'quoted_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_mobile.regex' => 'Enter a valid 10-digit mobile number', 'delivery_mobile.regex' => 'Enter a valid 10-digit mobile number',
            'pickup_pincode.digits' => 'Enter a valid 6-digit pincode', 'delivery_pincode.digits' => 'Enter a valid 6-digit pincode',
            'item_photo.image' => 'The parcel photo must be an image', 'item_photo.max' => 'The photo must be 5 MB or smaller',
            '*.required' => 'This field is required',
        ];
    }
}
