<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\ApiFormRequest;
use App\Models\ServiceMarketplaceBooking;
use Illuminate\Validation\Rule;

class ListServiceBookingsRequest extends ApiFormRequest
{
    protected int $failureStatus = 400;

    public function rules(): array
    {
        return [
            'category' => ['nullable', Rule::in(array_keys(ServiceMarketplaceBooking::CATEGORIES))],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
