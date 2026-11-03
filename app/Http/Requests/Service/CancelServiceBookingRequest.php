<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\ApiFormRequest;

/** Only a reason is accepted: status, refund amount and payment ids are always decided on the server. */
class CancelServiceBookingRequest extends ApiFormRequest
{
    protected int $failureStatus = 400;

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}
