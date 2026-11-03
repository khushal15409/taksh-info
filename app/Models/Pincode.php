<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The postal directory (`pincodes`); `status` is whether Taksh delivers there. */
class Pincode extends Model
{
    protected $fillable = ['officename', 'pincode', 'delivery', 'district', 'statename', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }
}
