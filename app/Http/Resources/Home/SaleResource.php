<?php

namespace App\Http\Resources\Home;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class SaleResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sale_type' => $this->sale_type,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'discount_label' => $this->getDiscountLabel(),
            'start_at' => $this->start_at->toIso8601String(),
            'end_at' => $this->end_at->toIso8601String(),
            'priority' => $this->priority,
        ];
    }
}
