<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;

class PlaceSearchRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:3', 'max:200']];
    }
}
