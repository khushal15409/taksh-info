<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentView extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'product_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
