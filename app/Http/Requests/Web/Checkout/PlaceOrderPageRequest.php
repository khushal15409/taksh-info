<?php

namespace App\Http\Requests\Web\Checkout;

use App\Http\Requests\Web\WebFormRequest;

/** "Confirm Order": the chosen address and way to pay, plus the token of the checkout page it came from (see CheckoutIdempotency). */
class PlaceOrderPageRequest extends WebFormRequest
{
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:cod,online'],
            'checkout_token' => ['required', 'uuid'],
            'type' => ['nullable', 'in:normal,30_min'],
        ];
    }

    /** The cart being ordered: Standard (default) or Quick. */
    public function type(): string
    {
        return $this->input('type') === '30_min' ? '30_min' : 'normal';
    }

    public function messages(): array
    {
        return [
            'address_id.required' => 'Please select or add a delivery address',
            'address_id.integer' => 'Please select or add a delivery address',
            'payment_method.required' => 'Choose a payment method',
            'payment_method.in' => 'Choose a payment method',
            'checkout_token.*' => 'Your checkout page expired. Please review your order and try again.',
        ];
    }
}
