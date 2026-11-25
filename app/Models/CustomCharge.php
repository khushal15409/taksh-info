<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One admin-defined labelled charge (Settings > App Charges). */
class CustomCharge extends Model
{
    protected $fillable = ['label', 'enabled', 'applies_to', 'type', 'default_amount', 'ranges', 'sort_order',
        'scope_mode', 'scope_min_price', 'scope_max_price', 'scope_category_ids'];

    protected $casts = [
        'enabled' => 'boolean',
        'default_amount' => 'float',
        'ranges' => 'array',
        'scope_min_price' => 'float',
        'scope_max_price' => 'float',
        'scope_category_ids' => 'array',
    ];

    public function overrides()
    {
        return $this->hasMany(CustomChargeOverride::class);
    }
}
