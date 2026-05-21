<?php

namespace App\Modules\Loyalty\Listeners;

use App\Models\User;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\PaymentSucceeded;
use App\Modules\Orders\Events\RefundIssued;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecalculateUserLoyaltyListener implements ShouldQueue
{
    public string $queue = 'loyalty';

    public function __construct(
        private readonly LoyaltyService $loyaltyService,
    ) {
    }

    public function handle(OrderCreated|OrderCompleted|PaymentSucceeded|RefundIssued $event): void
    {
        $user = $event->order->customer;

        if (! $user instanceof User) {
            $user = $event->order->loadMissing('customer')->customer;
        }

        if (! $user instanceof User) {
            return;
        }

        $this->loyaltyService->upgradeUserIfEligible($user, $event);
    }
}