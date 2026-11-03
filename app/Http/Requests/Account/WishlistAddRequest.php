<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;

class WishlistAddRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['product_id' => ['required', 'integer', 'exists:products,id']];
    }
}
