<?php

namespace App\Policies;

use App\Models\LinkedAccount;
use App\Models\User;

class LinkedAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    public function create(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    public function view(User $user, LinkedAccount $linkedAccount): bool
    {
        return $user->isCustomerAccount()
            && (int) $linkedAccount->user_id === (int) $user->id;
    }

    public function update(User $user, LinkedAccount $linkedAccount): bool
    {
        return $this->view($user, $linkedAccount);
    }

    public function delete(User $user, LinkedAccount $linkedAccount): bool
    {
        return $this->view($user, $linkedAccount);
    }

    public function search(User $user): bool
    {
        return $user->isCustomerAccount();
    }
}
