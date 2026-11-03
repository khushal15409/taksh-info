<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAnswer extends Model
{
    protected function casts(): array
    {
        return ['is_approved' => 'boolean'];
    }
}
