<?php

namespace App\Policies;

use App\Models\SavedVehicle;
use App\Models\User;

class SavedVehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    public function create(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    public function view(User $user, SavedVehicle $savedVehicle): bool
    {
        return $user->isCustomerAccount()
            && (int) $savedVehicle->user_id === (int) $user->id;
    }

    public function update(User $user, SavedVehicle $savedVehicle): bool
    {
        return $this->view($user, $savedVehicle);
    }

    public function delete(User $user, SavedVehicle $savedVehicle): bool
    {
        return $this->view($user, $savedVehicle);
    }
}
