<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Row of the shared Spatie `roles` table (read and created here, never migrated). */
class Role extends Model
{
    protected $fillable = ['name', 'guard_name'];
}
