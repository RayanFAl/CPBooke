<?php

namespace App\Modules\CustomerWallets\Listeners;

use App\Models\User;
use App\Modules\CustomerWallets\Services\CustomerWalletService;
use Illuminate\Auth\Events\Registered;

class InitializeCustomerWalletsOnRegistrationListener
{
    public function __construct(
        private readonly CustomerWalletService $walletService,
    ) {
    }

    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->isCustomerAccount()) {
            return;
        }

        $this->walletService->ensureSupportedWallets($user);
    }
}
