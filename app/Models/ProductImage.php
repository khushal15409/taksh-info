<?php

namespace App\Models;

use App\Traits\HasMediaUrls;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasMediaUrls;

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function getImageUrlAttribute($value): ?string
    {
        return $this->getImageUrl($value, 'product');
    }
}
