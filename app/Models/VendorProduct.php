<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorProduct extends Model
{
    protected $fillable = ['vendor_id', 'product_variant_id', 'stock_qty', 'reserved_qty'];
}
