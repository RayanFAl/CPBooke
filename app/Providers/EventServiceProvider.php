<?php

namespace App\Providers;

use App\Modules\Admin\Finance\Events\CriticalFinanceAnomaliesDetected;
use App\Modules\Admin\Support\Events\SupportTicketAssigned;
use App\Modules\Admin\Support\Events\SupportTicketCreated;
use App\Modules\Admin\Support\Events\SupportTicketReplied;
use App\Modules\Admin\Support\Events\SupportTicketStatusChanged;
use App\Modules\Loyalty\Events\LoyaltyTierChanged;
use App\Modules\Admin\Support\Listeners\SupportEventLoggerListener;
use App\Modules\Loyalty\Listeners\RecalculateUserLoyaltyListener;
use App\Modules\Notifications\Listeners\DispatchSystemNotificationListener;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Events\OrderConfirmed;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\PaymentSucceeded;
use App\Modules\Orders\Events\RefundIssued;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        SupportTicketCreated::class => [
            SupportEventLoggerListener::class,
            DispatchSystemNotificationListener::class,
        ],
        SupportTicketReplied::class => [
            SupportEventLoggerListener::class,
            DispatchSystemNotificationListener::class,
        ],
        SupportTicketStatusChanged::class => [
            SupportEventLoggerListener::class,
        ],
        SupportTicketAssigned::class => [
            SupportEventLoggerListener::class,
            DispatchSystemNotificationListener::class,
        ],
        OrderCreated::class => [
            DispatchSystemNotificationListener::class,
            RecalculateUserLoyaltyListener::class,
        ],
        OrderConfirmed::class => [
            DispatchSystemNotificationListener::class,
        ],
        OrderCompleted::class => [
            RecalculateUserLoyaltyListener::class,
        ],
        PaymentSucceeded::class => [
            DispatchSystemNotificationListener::class,
            RecalculateUserLoyaltyListener::class,
        ],
        RefundIssued::class => [
            DispatchSystemNotificationListener::class,
            RecalculateUserLoyaltyListener::class,
        ],
        LoyaltyTierChanged::class => [
            DispatchSystemNotificationListener::class,
        ],
        CriticalFinanceAnomaliesDetected::class => [
            DispatchSystemNotificationListener::class,
        ],
    ];
}