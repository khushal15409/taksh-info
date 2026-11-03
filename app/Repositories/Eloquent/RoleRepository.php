<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Reads and writes the Spatie role tables directly (roles + model_has_roles) so the site
 * assigns roles exactly the way taksh-admin does, without pulling in the Spatie package.
 */
class RoleRepository implements RoleRepositoryInterface
{
    private const GUARD = 'web';

    public function hasAnyRole(User $user, array $names): bool
    {
        return $this->query($user)->whereIn('roles.name', $names)->exists();
    }

    public function hasRole(User $user, string $name): bool
    {
        return $this->query($user)->where('roles.name', $name)->exists();
    }

    public function assignRole(User $user, string $name): void
    {
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);

        DB::table('model_has_roles')->insertOrIgnore([
            'role_id' => $role->id,
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->getKey(),
        ]);
    }

    private function query(User $user)
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->getKey());
    }
}
