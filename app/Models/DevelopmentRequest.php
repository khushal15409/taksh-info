<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An app / website development enquiry (shared `development_requests`). */
class DevelopmentRequest extends Model
{
    protected $fillable = ['user_id', 'name', 'mobile', 'email', 'description', 'request_type', 'status'];
}
