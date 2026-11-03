<?php

namespace App\Http\Requests\Web\Cart;

use App\Http\Requests\Web\WebFormRequest;

/** A cart line's new quantity (0 removes it) and which cart (Standard or Quick) the page is showing. */
class CartLineRequest extends WebFormRequest
{
    public function rules(): array
    {
        return ['qty' => ['required', 'integer', 'min:0', 'max:1000'], 'type' => ['nullable', 'in:normal,30_min']];
    }

    public function type(): string
    {
        return $this->input('type', 'normal');
    }
}
