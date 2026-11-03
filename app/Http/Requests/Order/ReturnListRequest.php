<?php

namespace App\Http\Requests\Order;

use App\Enums\ReturnStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ReturnListRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['status' => ['nullable', Rule::in(array_column(ReturnStatus::cases(), 'value'))], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
