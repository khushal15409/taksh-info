<?php

namespace App\Http\Resources\Notification;

use App\Http\Resources\ApiResource;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $at = $this->sent_at ?? $this->created_at;

        return [
            'id' => $this->id,
            'type' => array_key_exists((string) $this->type, Notification::TYPES) ? $this->type : 'app',
            'title' => $this->title,
            'message' => $this->message,
            'is_read' => (bool) ($this->is_read ?? false),
            'sent_at' => $at?->toIso8601String(),
        ];
    }
}
