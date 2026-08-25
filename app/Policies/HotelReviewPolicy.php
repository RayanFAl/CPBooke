<?php

namespace App\Policies;

use App\Models\HotelReview;
use App\Models\User;

class HotelReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    public function create(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    public function view(User $user, HotelReview $hotelReview): bool
    {
        return $user->isCustomerAccount()
            && (int) $hotelReview->user_id === (int) $user->id;
    }
}
