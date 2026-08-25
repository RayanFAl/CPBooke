<?php

namespace App\Providers;

use App\Modules\Admin\Finance\Events\CriticalFinanceAnomaliesDetected;
use App\Modules\Admin\Support\Events\SupportTicketAssigned;
use App\Modules\Admin\Support\Events\SupportTicketCreated;
use App\Modules\Admin\Support\Events\SupportTicketReplied;
use App\Modules\Admin\Support\Events\SupportTicketStatusChanged;
use App\Modules\Admin\Support\Listeners\SupportEventLoggerListener;
use App\Modules\Loyalty\Events\LoyaltyTierChanged;
use App\Modules\Loyalty\Listeners\InitializeUserLoyaltyOnRegistrationListener;
use App\Modules\Loyalty\Listeners\RecalculateUserLoyaltyListener;
use App\Modules\Notifications\Events\AbandonedFlightSearchDue;
use App\Modules\Notifications\Events\PassengerActionDue;
use App\Modules\Notifications\Events\PriceAlertHit;
use App\Modules\Notifications\Listeners\DispatchSystemNotificationListener;
use App\Modules\Orders\Events\BookingReminderDue;
use App\Modules\Orders\Events\FlightStatusUpdated;
use App\Modules\Orders\Events\HotelStatusUpdated;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Events\OrderConfirmed;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\PaymentFailed;
use App\Modules\Orders\Events\PaymentSucceeded;
use App\Modules\Orders\Events\RefundFailed;
use App\Modules\Orders\Events\RefundInitiated;
use App\Modules\Orders\Events\RefundIssued;
use App\Modules\Airports\Listeners\RecordAirportTravelOnOrderConfirmed;
use App\Modules\Notifications\Listeners\MarkTravelSearchConvertedOnOrderConfirmed;
use App\Modules\Partners\Listeners\DispatchPartnerWebhookListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            InitializeUserLoyaltyOnRegistrationListener::class,
        ],
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
            DispatchSystemNotificationListener::class,
        ],
        SupportTicketAssigned::class => [
            SupportEventLoggerListener::class,
            DispatchSystemNotificationListener::class,
        ],
        OrderCreated::class => [
            DispatchSystemNotificationListener::class,
            DispatchPartnerWebhookListener::class,
            RecalculateUserLoyaltyListener::class,
        ],
        OrderConfirmed::class => [
            DispatchSystemNotificationListener::class,
            DispatchPartnerWebhookListener::class,
            RecordAirportTravelOnOrderConfirmed::class,
            MarkTravelSearchConvertedOnOrderConfirmed::class,
        ],
        OrderCompleted::class => [
            RecalculateUserLoyaltyListener::class,
        ],
        PaymentSucceeded::class => [
            DispatchSystemNotificationListener::class,
            DispatchPartnerWebhookListener::class,
            RecalculateUserLoyaltyListener::class,
        ],
        PaymentFailed::class => [
            DispatchSystemNotificationListener::class,
        ],
        FlightStatusUpdated::class => [
            DispatchSystemNotificationListener::class,
        ],
        HotelStatusUpdated::class => [
            DispatchSystemNotificationListener::class,
        ],
        OrderCancelled::class => [
            DispatchSystemNotificationListener::class,
        ],
        BookingReminderDue::class => [
            DispatchSystemNotificationListener::class,
        ],
        AbandonedFlightSearchDue::class => [
            DispatchSystemNotificationListener::class,
        ],
        PriceAlertHit::class => [
            DispatchSystemNotificationListener::class,
        ],
        PassengerActionDue::class => [
            DispatchSystemNotificationListener::class,
        ],
        RefundIssued::class => [
            DispatchSystemNotificationListener::class,
            DispatchPartnerWebhookListener::class,
            RecalculateUserLoyaltyListener::class,
        ],
        RefundInitiated::class => [
            DispatchSystemNotificationListener::class,
        ],
        RefundFailed::class => [
            DispatchSystemNotificationListener::class,
        ],
        LoyaltyTierChanged::class => [
            DispatchSystemNotificationListener::class,
        ],
        CriticalFinanceAnomaliesDetected::class => [
            DispatchSystemNotificationListener::class,
        ],
    ];
}
