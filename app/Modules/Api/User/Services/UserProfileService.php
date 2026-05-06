<?php

namespace App\Modules\Api\User\Services;

use App\Models\User;

class UserProfileService
{
    /**
     * Build the API-safe payload for a user profile.
     *
     * @return array<string, mixed>
     */
    public function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->full_name ?: $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
            'is_active' => (bool) $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Update the authenticated user profile.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->fill([
            'name' => $data['name'],
            'full_name' => $data['name'],
            'phone' => $data['phone'] ?: null,
            'country' => $data['country'] ?: null,
        ])->save();

        return $user->refresh();
    }
}