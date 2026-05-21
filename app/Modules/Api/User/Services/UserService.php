<?php

namespace App\Modules\Api\User\Services;

use App\Modules\Api\DTO\UpdateProfileDTO;
use App\Models\User;
use App\Modules\Loyalty\Services\LoyaltyService;

class UserService
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService,
    ) {
    }

    /**
     * Return the authenticated user's current profile entity.
     */
    public function profile(User $user): User
    {
        return tap($user, function (User $profile): void {
            $profile->setAttribute('loyalty', $this->loyaltyService->profilePayload($profile));
        });
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

        return tap($user->refresh(), function (User $profile): void {
            $profile->setAttribute('loyalty', $this->loyaltyService->profilePayload($profile));
        });
    }
}