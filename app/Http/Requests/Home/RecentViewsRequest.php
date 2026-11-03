<?php

namespace App\Http\Requests\Home;

use App\Http\Requests\ApiFormRequest;

class RecentViewsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['delivery_type' => ['nullable', 'string', 'max:30']];
    }

    /** standard_delivery | express_30 (also "express-30"); anything else means all products. */
    public function deliveryType(): ?string
    {
        $type = strtolower(trim((string) $this->query('delivery_type', '')));
        $type = $type === 'express-30' ? 'express_30' : $type;

        return in_array($type, ['standard_delivery', 'express_30'], true) ? $type : null;
    }
}
