<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A vendor registration request (shared `vendor_requests`). */
class VendorRequest extends Model
{
    protected $fillable = ['user_id', 'name', 'mobile', 'email', 'description', 'business_type', 'status'];
}
