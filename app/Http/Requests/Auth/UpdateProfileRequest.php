<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesProfile;

class UpdateProfileRequest extends ApiFormRequest
{
    use ValidatesProfile;

    protected int $failureStatus = 400;
}
