<?php

namespace App\Http\Requests\Wallet;

use App\Http\Requests\ApiFormRequest;

class PerPageRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 15);
    }
}
