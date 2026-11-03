<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    /** Find the user with this mobile or create a verified, active customer with the default name. */
    public function firstOrCreateCustomer(string $mobile): User;

    /** Persist pending changes on a user. */
    public function save(User $user): User;

    public function update(User $user, array $attributes): User;
}
