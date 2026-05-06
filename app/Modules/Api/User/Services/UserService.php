<?php

namespace App\Modules\Api\User\Services;

use App\Modules\Api\DTO\UpdateProfileDTO;
use App\Models\User;

class UserService
{
    /**
     * Return the authenticated user's current profile entity.
     */
    public function profile(User $user): User
    {
        return $user;
    }

    /**
     * Update the authenticated user profile.
     */
    public function update(User $user, UpdateProfileDTO $data): User
    {
        $user->fill([
            'name' => $data->name,
            'full_name' => $data->name,
            'phone' => $data->phone,
            'country' => $data->country,
        ])->save();

        return $user->refresh();
    }
}