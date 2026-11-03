<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAttribute extends Model
{
    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }
}
