<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A product's or category's own amount for one custom charge. */
class CustomChargeOverride extends Model
{
    protected $fillable = ['custom_charge_id', 'scope', 'scope_id', 'amount', 'type'];

    protected $casts = ['amount' => 'float'];
}
