<?php

namespace App\Http\Requests\Web\Orders;

use App\Http\Requests\Web\WebFormRequest;

/** The return form: which item, how many, why, what the customer wants, and up to five photos. The service checks the order is theirs. */
class StoreReturnPageRequest extends WebFormRequest
{
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'min:1'],
            'order_item_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'resolution' => ['required', 'in:refund,replacement,wallet'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please describe the problem (at least 10 characters)',
            'reason.min' => 'Please describe the problem (at least 10 characters)',
            'order_item_id.required' => 'Choose the item you want to return',
            'images.max' => 'Only 5 photos allowed',
            'images.*.image' => 'Photos must be JPG, PNG, WEBP or GIF',
            'images.*.mimes' => 'Photos must be JPG, PNG, WEBP or GIF',
            'images.*.max' => 'Each photo must be 5 MB or smaller',
        ];
    }

    public function files5(): array
    {
        return array_values(array_filter((array) $this->file('images')));
    }
}
