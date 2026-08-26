<?php

namespace App\Policies;

use App\Models\LinkedAccountRequest;
use App\Models\User;

class LinkedAccountRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    public function respond(User $user, LinkedAccountRequest $linkedAccountRequest): bool
    {
        return $user->isCustomerAccount()
            && (int) $linkedAccountRequest->to_user_id === (int) $user->id;
    }
}
