<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row of an order's timeline (shared table with taksh-admin). */
class OrderStatusLog extends Model
{
    protected $fillable = ['order_id', 'status', 'changed_by', 'changed_by_user_id', 'notes'];
}
