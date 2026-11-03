<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Express30LogoSetting extends Model
{
    protected $table = 'express_30_logo_settings';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
