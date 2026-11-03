<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A customer asking Taksh to call them back (shared `callback_requests`; the admin works the queue). */
class CallbackRequest extends Model
{
    protected $fillable = ['user_id', 'name', 'mobile', 'status'];
}
