<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderCharge extends Model
{
    protected $fillable = ['order_id', 'type', 'label', 'amount', 'is_discount'];
}
