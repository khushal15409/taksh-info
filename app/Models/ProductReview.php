<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $fillable = ['product_id', 'user_id', 'rating', 'review_title', 'review_text', 'is_verified_purchase', 'is_approved'];

    protected function casts(): array
    {
        return ['is_approved' => 'boolean', 'is_verified_purchase' => 'boolean'];
    }
}
