<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-customer read state (panel = customer). */
class NotificationRecipient extends Model
{
    protected $fillable = ['notification_id', 'panel', 'recipient_user_id', 'seen'];

    protected function casts(): array
    {
        return ['seen' => 'boolean'];
    }
}
