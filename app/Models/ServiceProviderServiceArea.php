<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row = a provider serves a service in one pincode. */
class ServiceProviderServiceArea extends Model
{
    protected $fillable = ['service_provider_id', 'service_service_id', 'pincode_id'];

    public function pincode()
    {
        return $this->belongsTo(Pincode::class, 'pincode_id');
    }
}
