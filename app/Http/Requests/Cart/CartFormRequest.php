<?php

namespace App\Http\Requests\Cart;

use App\Http\Requests\ApiFormRequest;

abstract class CartFormRequest extends ApiFormRequest
{
    /** The delivery type code a request names ('30_min' = quick; anything else = standard); null when none. */
    public function deliveryType(): ?string
    {
        $type = $this->input('delivery_type');

        return is_string($type) && $type !== '' ? $type : null;
    }
}
