<?php

namespace App\Policies;

use App\Models\SavedPassenger;
use App\Models\User;

class SavedPassengerPolicy
{
    /**
     * Determine whether a customer can list saved passengers.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    /**
     * Determine whether a customer can create saved passengers.
     */
    public function create(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    /**
     * Determine whether a customer can view a saved passenger.
     */
    public function view(User $user, SavedPassenger $savedPassenger): bool
    {
        return $user->isCustomerAccount()
            && (int) $savedPassenger->user_id === (int) $user->id;
    }

    /**
     * Determine whether a customer can update a saved passenger.
     */
    public function update(User $user, SavedPassenger $savedPassenger): bool
    {
        return $this->view($user, $savedPassenger);
    }

    /**
     * Determine whether a customer can delete a saved passenger.
     */
    public function delete(User $user, SavedPassenger $savedPassenger): bool
    {
        return $this->view($user, $savedPassenger);
    }
}
