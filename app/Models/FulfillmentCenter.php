<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FulfillmentCenter extends Model
{
    protected $table = 'fulfillment_centers';

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'supports_express_30' => 'boolean',
        ];
    }

    /** Active centers within $radius km of a point, nearest first (distance in `distance`). */
    public function scopeNearest($query, $latitude, $longitude, $radius = 15)
    {
        return $query->select('fulfillment_centers.*')
            ->selectRaw(
                '(6371 * acos(LEAST(1, GREATEST(-1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))) AS distance',
                [$latitude, $longitude, $latitude]
            )
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->havingRaw('distance <= ?', [$radius])
            ->orderBy('distance', 'asc');
    }
}
