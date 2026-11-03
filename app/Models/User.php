<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Maps the shared Taksh `users` table (also used by taksh-admin and the mobile API).
 * Only the columns the website reads or writes are listed; the table itself is never migrated from here.
 */
class User extends Authenticatable
{
    use Notifiable;

    public const TYPE_CUSTOMER = 'customer';

    public const STATUS_ACTIVE = 'active';

    /** Roles that must never be overwritten or downgraded by a customer login. */
    public const STAFF_ROLES = ['super-admin', 'admin', 'vendor', 'salesman'];

    protected $fillable = [
        'mobile',
        'name',
        'f_name',
        'l_name',
        'email',
        'password',
        'image',
        'is_verified',
        'status',
        'user_type',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
