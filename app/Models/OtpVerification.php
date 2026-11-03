<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One texted code (hashed) in the shared `otp_verifications` table. */
class OtpVerification extends Model
{
    protected $fillable = ['mobile', 'otp', 'context', 'expires_at', 'is_used'];

    protected $hidden = ['otp', 'otp_encrypted'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_used' => 'boolean',
        ];
    }
}
