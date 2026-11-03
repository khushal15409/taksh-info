<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnStatusLog extends Model
{
    protected $fillable = ['return_id', 'status', 'notes', 'changed_by'];
}
