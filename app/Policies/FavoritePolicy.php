<?php

namespace App\Policies;

use App\Models\Favorite;
use App\Models\User;

class FavoritePolicy
{
    /**
     * Determine whether a customer can list favorites.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    /**
     * Determine whether a customer can create favorites.
     */
    public function create(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    /**
     * Determine whether a customer can view a favorite.
     */
    public function view(User $user, Favorite $favorite): bool
    {
        return $user->isCustomerAccount()
            && (int) $favorite->user_id === (int) $user->id;
    }

    /**
     * Determine whether a customer can delete a favorite.
     */
    public function delete(User $user, Favorite $favorite): bool
    {
        return $this->view($user, $favorite);
    }
}
