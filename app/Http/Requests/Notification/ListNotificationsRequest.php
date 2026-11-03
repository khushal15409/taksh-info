<?php

namespace App\Http\Requests\Notification;

use App\Http\Requests\ApiFormRequest;

class ListNotificationsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'filter' => ['nullable', 'in:all,unread,offers,orders'],
        ];
    }
}
