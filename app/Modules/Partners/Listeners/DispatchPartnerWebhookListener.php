<?php

namespace App\Modules\Partners\Listeners;

use App\Modules\Orders\Events\OrderConfirmed;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\PaymentSucceeded;
use App\Modules\Orders\Events\RefundIssued;
use App\Modules\Partners\Services\PartnerWebhookDispatcher;
use App\Modules\Partners\Support\PartnerWebhookEvents;

class DispatchPartnerWebhookListener
{
    public function __construct(
        private readonly PartnerWebhookDispatcher $dispatcher,
    ) {
    }

    public function handle(object $event): void
    {
        match (true) {
            $event instanceof OrderCreated => $this->dispatcher->dispatchOrderEvent(
                PartnerWebhookEvents::ORDER_CREATED,
                $event->order,
            ),
            $event instanceof OrderConfirmed => $this->dispatcher->dispatchOrderEvent(
                PartnerWebhookEvents::ORDER_CONFIRMED,
                $event->order,
            ),
            $event instanceof PaymentSucceeded => $this->dispatcher->dispatchOrderEvent(
                PartnerWebhookEvents::PAYMENT_SUCCEEDED,
                $event->order,
                $event->transaction,
            ),
            $event instanceof RefundIssued => $this->dispatcher->dispatchOrderEvent(
                PartnerWebhookEvents::REFUND_ISSUED,
                $event->order,
                $event->transaction,
            ),
            default => null,
        };
    }
}
