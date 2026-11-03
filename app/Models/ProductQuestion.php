<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductQuestion extends Model
{
    protected function casts(): array
    {
        return ['is_approved' => 'boolean'];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ProductAnswer::class, 'question_id');
    }
}
