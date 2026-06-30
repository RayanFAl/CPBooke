<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether a customer can create orders via API.
     */
    public function create(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    /**
     * Determine whether a customer can view their orders index.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCustomerAccount();
    }

    /**
     * Determine whether a customer can view a specific order.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->isCustomerAccount()
            && (int) $order->customer_id === (int) $user->id;
    }
}