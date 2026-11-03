<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function firstOrCreateCustomer(string $mobile): User
    {
        return User::firstOrCreate(
            ['mobile' => $mobile],
            [
                'name' => 'User '.$mobile,
                'user_type' => User::TYPE_CUSTOMER,
                'is_verified' => true,
                'status' => User::STATUS_ACTIVE,
            ]
        );
    }

    public function save(User $user): User
    {
        $user->save();

        return $user;
    }

    public function update(User $user, array $attributes): User
    {
        $user->fill($attributes)->save();

        return $user;
    }
}
