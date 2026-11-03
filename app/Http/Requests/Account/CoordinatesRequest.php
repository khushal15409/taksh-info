<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;

class CoordinatesRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180']];
    }
}
