<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A push token (FCM / web push) registered by a logged-in customer. */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token', 'token_hash', 'platform', 'app', 'is_active', 'last_used_at'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_used_at' => 'datetime'];
    }

    public static function hashOf(string $token): string
    {
        return hash('sha256', $token);
    }
}
