<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    protected $fillable = ['return_id', 'product_variant_id', 'quantity', 'price', 'notes'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'quantity' => 'integer'];
    }
}
