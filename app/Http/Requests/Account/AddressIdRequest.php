<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;

class AddressIdRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['address_id' => ['required', 'integer', 'exists:addresses,id']];
    }
}
