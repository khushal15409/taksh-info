<?php

namespace App\Http\Resources\Cart;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/** The cart bill as the API sends it: items, total, delivery, fee/coupon charges and total_with_delivery. */
class CartResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'items' => $this->items,
            'total' => $this->total,
            'delivery_charge' => $this->delivery,
            'charges' => $this->charges,
            'coupon' => $this->coupon,
            'total_with_delivery' => $this->total_with_delivery,
        ];
    }
}
