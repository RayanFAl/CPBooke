<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Admin\Finance\Events\CriticalFinanceAnomaliesDetected;
use App\Modules\Admin\Support\Events\SupportTicketAssigned;
use App\Modules\Admin\Support\Events\SupportTicketCreated;
use App\Modules\Admin\Support\Events\SupportTicketReplied;
use App\Modules\Admin\Support\Events\SupportTicketStatusChanged;
use App\Modules\Loyalty\Events\LoyaltyTierChanged;
use App\Modules\Orders\Events\OrderConfirmed;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\PaymentSucceeded;
use App\Modules\Orders\Events\RefundIssued;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchSystemNotificationListener implements ShouldQueue
{
    public string $queue = 'notifications-dispatch';

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function handle(
        OrderCreated|OrderConfirmed|PaymentSucceeded|RefundIssued|SupportTicketCreated|SupportTicketReplied|SupportTicketAssigned|SupportTicketStatusChanged|LoyaltyTierChanged|CriticalFinanceAnomaliesDetected $event,
    ): void {
        $this->notificationService->dispatchForEvent($event);
    }
}