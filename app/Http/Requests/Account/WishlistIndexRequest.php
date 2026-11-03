<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;

class WishlistIndexRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
