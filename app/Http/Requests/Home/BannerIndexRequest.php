<?php

namespace App\Http\Requests\Home;

use App\Http\Requests\ApiFormRequest;

class BannerIndexRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'position' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:20'],   // anything other than standard|quick|service is ignored, like the API
            'device_type' => ['nullable', 'in:desktop,mobile'],
            'fc_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
