<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\ApiFormRequest;

/** Join as a delivery partner (public). */
class JoinRequestRequest extends ApiFormRequest
{
    protected int $failureStatus = 400;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
