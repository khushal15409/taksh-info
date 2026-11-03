<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\ApiFormRequest;

class UploadReturnMediaRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'return_id' => ['required', 'exists:returns,id'],
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ];
    }
}
