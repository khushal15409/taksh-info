<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Someone applying to join as a delivery partner (shared `delivery_boy_join_requests`). */
class DeliveryBoyJoinRequest extends Model
{
    protected $fillable = ['name', 'mobile', 'email', 'address', 'pincode', 'description', 'status', 'admin_remark'];
}
