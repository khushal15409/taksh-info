<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Shared `addresses` table. `type` is stored as home / work / other ("office" is saved as "work"). */
class Address extends Model
{
    protected $fillable = [
        'user_id', 'state_id', 'city_id', 'area_id', 'name', 'mobile', 'address_line_1', 'address_line_2',
        'pincode', 'landmark', 'latitude', 'longitude', 'type', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'latitude' => 'decimal:8', 'longitude' => 'decimal:8'];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
