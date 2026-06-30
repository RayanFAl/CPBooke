<?php

namespace App\Modules\Loyalty\Listeners;

use App\Models\User;
use App\Modules\Loyalty\Services\LoyaltyService;
use Illuminate\Auth\Events\Registered;

class InitializeUserLoyaltyOnRegistrationListener
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService,
    ) {
    }

    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->isCustomerAccount()) {
            return;
        }

        $this->loyaltyService->upgradeUserIfEligible($user, $event);
    }
}
