<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A verified professional (read-only here: assignment is done in the admin panel). */
class ServiceProvider extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'business_name', 'profile_image', 'pincode', 'verification_status', 'is_active', 'is_available'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_available' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function services()
    {
        return $this->belongsToMany(ServiceService::class, 'service_provider_services', 'service_provider_id', 'service_service_id')->withPivot('price', 'is_active');
    }

    public function serviceAreas()
    {
        return $this->hasMany(ServiceProviderServiceArea::class, 'service_provider_id');
    }
}
