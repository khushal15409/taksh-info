<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface RoleRepositoryInterface
{
    /** Does the user hold at least one of these roles? */
    public function hasAnyRole(User $user, array $names): bool;

    public function hasRole(User $user, string $name): bool;

    /** Give the user the role, creating the role (web guard) when it does not exist yet. */
    public function assignRole(User $user, string $name): void;
}
