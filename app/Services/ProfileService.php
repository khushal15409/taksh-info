<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class ProfileService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * Same field mapping as the API: first/last name are stored in f_name/l_name and
     * `name` is kept in step for older screens; email is only touched when it was sent.
     *
     * @param  array{first_name: string, last_name: string, mobile: string, email?: ?string}  $data
     */
    public function update(User $user, array $data): User
    {
        $attributes = [
            'f_name' => $data['first_name'],
            'l_name' => $data['last_name'],
            'mobile' => $data['mobile'],
            'name' => trim($data['first_name'].' '.$data['last_name']),
        ];

        if (array_key_exists('email', $data)) {
            $attributes['email'] = $data['email'];
        }

        return $this->users->update($user, $attributes);
    }
}
