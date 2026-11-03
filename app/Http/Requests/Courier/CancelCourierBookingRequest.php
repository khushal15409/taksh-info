<?php

namespace App\Http\Requests\Courier;

use App\Http\Requests\ApiFormRequest;

class CancelCourierBookingRequest extends ApiFormRequest
{
    protected int $failureStatus = 400;

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}
