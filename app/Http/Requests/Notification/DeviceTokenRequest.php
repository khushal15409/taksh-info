<?php

namespace App\Http\Requests\Notification;

use App\Http\Requests\ApiFormRequest;

/** POST registers a token (with a platform); DELETE only needs the token. */
class DeviceTokenRequest extends ApiFormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('delete')) {
            return ['token' => ['required', 'string', 'max:4096']];
        }

        return [
            'token' => ['required', 'string', 'min:20', 'max:4096'],
            'platform' => ['nullable', 'string', 'in:web,android,ios'],
        ];
    }
}
