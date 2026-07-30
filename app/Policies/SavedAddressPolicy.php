<?php

namespace App\Policies;

use App\Models\SavedAddress;
use App\Models\User;

class SavedAddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    public function create(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    public function view(User $user, SavedAddress $savedAddress): bool
    {
        return $user->isCustomerAccount()
            && (int) $savedAddress->user_id === (int) $user->id;
    }

    public function update(User $user, SavedAddress $savedAddress): bool
    {
        return $this->view($user, $savedAddress);
    }

    public function delete(User $user, SavedAddress $savedAddress): bool
    {
        return $this->view($user, $savedAddress);
    }
}
