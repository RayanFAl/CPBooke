<?php

namespace App\Modules\Notifications\Support;

use App\Models\User;
use App\Modules\Admin\Finance\Events\CriticalFinanceAnomaliesDetected;
use App\Modules\Admin\Support\Events\SupportTicketAssigned;
use App\Modules\Admin\Support\Events\SupportTicketCreated;
use App\Modules\Admin\Support\Events\SupportTicketReplied;
use App\Modules\Admin\Support\Events\SupportTicketStatusChanged;
use App\Modules\Loyalty\Events\LoyaltyTierChanged;
use App\Modules\Orders\Events\BookingReminderDue;
use App\Modules\Orders\Events\FlightStatusUpdated;
use App\Modules\Orders\Events\OrderConfirmed;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\PaymentFailed;
use App\Modules\Orders\Events\PaymentSucceeded;
use App\Modules\Orders\Events\RefundIssued;
use App\Models\Order;

class NotificationDefinitionRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitionsFor(object $event): array
    {
        return match (true) {
            $event instanceof OrderCreated => [$this->orderCreatedDefinition($event)],
            $event instanceof OrderConfirmed => [$this->orderConfirmedDefinition($event)],
            $event instanceof PaymentSucceeded => [$this->paymentSucceededDefinition($event)],
            $event instanceof PaymentFailed => [$this->paymentFailedDefinition($event)],
            $event instanceof RefundIssued => [$this->refundIssuedDefinition($event)],
            $event instanceof FlightStatusUpdated => [$this->flightStatusUpdatedDefinition($event)],
            $event instanceof BookingReminderDue => [$this->bookingReminderDefinition($event)],
            $event instanceof SupportTicketCreated => $this->supportTicketCreatedDefinitions($event),
            $event instanceof SupportTicketReplied => $this->supportTicketRepliedDefinitions($event),
            $event instanceof SupportTicketAssigned => $this->supportTicketAssignedDefinitions($event),
            $event instanceof SupportTicketStatusChanged => $this->supportTicketStatusChangedDefinitions($event),
            $event instanceof LoyaltyTierChanged => $this->loyaltyTierChangedDefinitions($event),
            $event instanceof CriticalFinanceAnomaliesDetected => $this->criticalFinanceAnomalyDefinitions($event),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function orderCreatedDefinition(OrderCreated $event): array
    {
        $ctx = OrderNotificationContext::base($event->order);
        $ctx['topic'] = null;

        return [
            'code' => 'ORDER_CREATED',
            'name' => 'Order Created',
            'subject' => 'Your booking {order_reference} was created — complete payment',
            'body' => 'Hello {user_name}, your booking #{order_reference} was created and is waiting for payment.',
            'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            'variables' => ['user_name', 'order_id', 'order_reference', 'order_status', 'service_type', 'deep_link'],
            'notification_type' => 'order',
            'topic' => null,
            'related_type' => 'order',
            'related_id' => $event->order->id,
            'users' => array_filter([$event->order->customer]),
            'payload' => $ctx,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderConfirmedDefinition(OrderConfirmed $event): array
    {
        $order = $event->order;
        $ctx = OrderNotificationContext::base($order);
        $isTicketed = $order->status === Order::STATUS_TICKETED;

        // Product preference gate only for hotel/insurance confirmations (H1/I1).
        $topic = match ($order->service_type) {
            Order::SERVICE_TYPE_HOTEL => NotificationTopics::HOTEL,
            Order::SERVICE_TYPE_INSURANCE => NotificationTopics::INSURANCE,
            default => null,
        };
        $ctx['topic'] = $topic;

        [$code, $name, $subject, $body] = match (true) {
            $isTicketed && $order->service_type === Order::SERVICE_TYPE_FLIGHT => [
                'FLIGHT_TICKET_ISSUED',
                'Flight Ticket Issued',
                'Your ticket for {order_reference} was issued',
                'Hello {user_name}, your flight ticket #{order_reference} has been issued successfully.',
            ],
            $order->service_type === Order::SERVICE_TYPE_HOTEL => [
                'HOTEL_BOOKING_CONFIRMED',
                'Hotel Booking Confirmed',
                'Your hotel booking {order_reference} is confirmed',
                'Hello {user_name}, your hotel booking #{order_reference} is confirmed.',
            ],
            $order->service_type === Order::SERVICE_TYPE_INSURANCE => [
                'INSURANCE_POLICY_ISSUED',
                'Insurance Policy Issued',
                'Your insurance policy {order_reference} was issued',
                'Hello {user_name}, your insurance policy #{order_reference} has been issued successfully.',
            ],
            $order->service_type === Order::SERVICE_TYPE_ESIM => [
                'ESIM_ORDER_CONFIRMED',
                'eSIM Order Confirmed',
                'Your eSIM order {order_reference} is ready',
                'Hello {user_name}, your eSIM order #{order_reference} is confirmed.',
            ],
            default => [
                'ORDER_CONFIRMED',
                'Order Confirmed',
                'Order {order_reference} is confirmed',
                'Hello {user_name}, your order #{order_reference} is now confirmed.',
            ],
        };

        return [
            'code' => $code,
            'name' => $name,
            'subject' => $subject,
            'body' => $body,
            'channels' => [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP],
            'variables' => ['user_name', 'order_id', 'order_reference', 'service_type', 'deep_link'],
            'notification_type' => OrderNotificationContext::inboxTypeForConfirmed($order),
            'topic' => $topic,
            'related_type' => 'order',
            'related_id' => $order->id,
            'users' => array_filter([$order->customer]),
            'payload' => $ctx,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSucceededDefinition(PaymentSucceeded $event): array
    {
        $ctx = OrderNotificationContext::base($event->order);
        $ctx['topic'] = null;

        return [
            'code' => 'PAYMENT_SUCCEEDED',
            'name' => 'Payment Succeeded',
            'subject' => 'Payment received for order {order_reference}',
            'body' => 'Hello {user_name}, we received {amount} {currency} for order #{order_reference}.',
            'channels' => [
                NotificationChannels::PUSH,
                NotificationChannels::EMAIL,
                NotificationChannels::IN_APP,
                NotificationChannels::SMS,
            ],
            'variables' => ['user_name', 'order_id', 'order_reference', 'amount', 'currency', 'service_type', 'deep_link'],
            'notification_type' => 'payment',
            'topic' => null,
            'related_type' => 'order',
            'related_id' => $event->order->id,
            'users' => array_filter([$event->order->customer]),
            'payload' => array_merge($ctx, [
                'amount' => number_format((float) $event->transaction->amount, 2, '.', ''),
                'currency' => $event->transaction->currency,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentFailedDefinition(PaymentFailed $event): array
    {
        $ctx = OrderNotificationContext::base($event->order);
        $ctx['topic'] = null;

        return [
            'code' => 'PAYMENT_FAILED',
            'name' => 'Payment Failed',
            'subject' => 'Payment failed for order {order_reference}',
            'body' => 'Hello {user_name}, payment for order #{order_reference} failed. Please try again. {reason}',
            'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL],
            'variables' => ['user_name', 'order_id', 'order_reference', 'reason', 'service_type', 'deep_link'],
            'notification_type' => 'payment',
            'topic' => null,
            'related_type' => 'order',
            'related_id' => $event->order->id,
            'users' => array_filter([$event->order->customer]),
            'payload' => array_merge($ctx, [
                'reason' => $event->reason ?: 'Please retry payment.',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function refundIssuedDefinition(RefundIssued $event): array
    {
        $ctx = OrderNotificationContext::base($event->order);
        $ctx['topic'] = null;
        $channels = [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::SMS];

        if (($event->transaction->metadata['source_context'] ?? null) === 'support_ticket') {
            $channels[] = NotificationChannels::WHATSAPP;
        }

        return [
            'code' => 'REFUND_ISSUED',
            'name' => 'Refund Issued',
            'subject' => 'Refund issued for order {order_reference}',
            'body' => 'Hello {user_name}, we issued a refund of {amount} {currency} for order #{order_reference}. Reason: {reason}',
            'channels' => array_values(array_unique($channels)),
            'variables' => ['user_name', 'order_id', 'order_reference', 'amount', 'currency', 'reason', 'service_type', 'deep_link'],
            'notification_type' => 'payment',
            'topic' => null,
            'related_type' => 'order',
            'related_id' => $event->order->id,
            'users' => array_filter([$event->order->customer]),
            'payload' => array_merge($ctx, [
                'amount' => number_format((float) $event->transaction->amount, 2, '.', ''),
                'currency' => $event->transaction->currency,
                'reason' => $event->transaction->reason ?: 'No reason provided.',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function flightStatusUpdatedDefinition(FlightStatusUpdated $event): array
    {
        $ctx = OrderNotificationContext::base($event->order);
        $summary = $event->summary ?: 'There is an update on your flight.';

        return [
            'code' => 'FLIGHT_STATUS_UPDATED',
            'name' => 'Flight Status Updated',
            'subject' => 'Update on your flight {order_reference}',
            'body' => 'Hello {user_name}, {summary} Booking #{order_reference}.',
            'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL],
            'variables' => ['user_name', 'order_id', 'order_reference', 'summary', 'service_type', 'deep_link'],
            'notification_type' => 'flight',
            'topic' => NotificationTopics::FLIGHT_UPDATES,
            'related_type' => 'order',
            'related_id' => $event->order->id,
            'users' => array_filter([$event->order->customer]),
            'payload' => array_merge($ctx, [
                'summary' => $summary,
                'changes' => $event->changes,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingReminderDefinition(BookingReminderDue $event): array
    {
        $ctx = OrderNotificationContext::base($event->order);

        [$code, $subject, $body, $type] = match ($event->window) {
            BookingReminderDue::WINDOW_3H => [
                'FLIGHT_REMINDER_3H',
                'Your flight is in 3 hours — {order_reference}',
                'Hello {user_name}, your flight #{order_reference} departs in about 3 hours.',
                'flight',
            ],
            BookingReminderDue::WINDOW_HOTEL_CHECKIN_24H => [
                'HOTEL_CHECKIN_REMINDER_24H',
                'Your stay is tomorrow — {order_reference}',
                'Hello {user_name}, check-in for hotel booking #{order_reference} is tomorrow.',
                'order',
            ],
            default => [
                'FLIGHT_REMINDER_24H',
                'Your flight is tomorrow — {order_reference}',
                'Hello {user_name}, your flight #{order_reference} departs in about 24 hours.',
                'flight',
            ],
        };

        $topic = $event->window === BookingReminderDue::WINDOW_HOTEL_CHECKIN_24H
            ? NotificationTopics::HOTEL
            : NotificationTopics::BOOKING_REMINDERS;

        // Hotel check-in reminders require both hotel + booking_reminders conceptually;
        // primary gate uses booking_reminders via dual-check in job before dispatch.
        if ($event->window === BookingReminderDue::WINDOW_HOTEL_CHECKIN_24H) {
            $topic = NotificationTopics::BOOKING_REMINDERS;
        }

        return [
            'code' => $code,
            'name' => 'Booking Reminder',
            'subject' => $subject,
            'body' => $body,
            'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            'variables' => ['user_name', 'order_id', 'order_reference', 'service_type', 'deep_link', 'reminder_window'],
            'notification_type' => $type,
            'topic' => $topic,
            'related_type' => 'order',
            'related_id' => $event->order->id,
            'users' => array_filter([$event->order->customer]),
            'payload' => array_merge($ctx, [
                'reminder_window' => $event->window,
                'idempotency_key' => $event->order->id.'|'.$code,
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function supportTicketCreatedDefinitions(SupportTicketCreated $event): array
    {
        $definitions = [[
            'code' => 'SUPPORT_TICKET_CREATED_CUSTOMER',
            'name' => 'Support Ticket Created For Customer',
            'subject' => 'Support ticket {ticket_number} was created',
            'body' => 'Hello {user_name}, your support ticket {ticket_number} was opened successfully. Our team will update you shortly.',
            'channels' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
            'variables' => ['user_name', 'ticket_number', 'ticket_subject', 'deep_link'],
            'notification_type' => 'system',
            'related_type' => 'support_ticket',
            'related_id' => $event->ticket->id,
            'users' => array_filter([$event->ticket->user]),
            'payload' => [
                'user_name' => $event->ticket->user?->full_name ?: $event->ticket->user?->name ?: 'Customer',
                'ticket_number' => $event->ticket->ticket_number,
                'ticket_subject' => $event->ticket->subject,
                'deep_link' => '/support/'.$event->ticket->id,
            ],
        ]];

        if ($event->ticket->assignee !== null) {
            $definitions[] = [
                'code' => 'SUPPORT_TICKET_CREATED_AGENT',
                'name' => 'Support Ticket Created For Assigned Agent',
                'subject' => 'New ticket {ticket_number} assigned to you',
                'body' => 'Ticket {ticket_number} is now in your queue. Subject: {ticket_subject}.',
                'channels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
                'variables' => ['ticket_number', 'ticket_subject'],
                'notification_type' => 'system',
                'related_type' => 'support_ticket',
                'related_id' => $event->ticket->id,
                'users' => array_filter([$event->ticket->assignee]),
                'payload' => [
                    'ticket_number' => $event->ticket->ticket_number,
                    'ticket_subject' => $event->ticket->subject,
                ],
            ];
        }

        return $definitions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function supportTicketRepliedDefinitions(SupportTicketReplied $event): array
    {
        $messageSenderIsAdmin = $event->message->user?->isAdminAccount() ?? false;

        if ($messageSenderIsAdmin) {
            return [[
                'code' => 'SUPPORT_TICKET_REPLIED_CUSTOMER',
                'name' => 'Support Ticket Replied For Customer',
                'subject' => 'New update on ticket {ticket_number}',
                'body' => 'Hello {user_name}, support replied to ticket {ticket_number}.',
                'channels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
                'variables' => ['user_name', 'ticket_number', 'deep_link'],
                'notification_type' => 'system',
                'related_type' => 'support_ticket',
                'related_id' => $event->ticket->id,
                'users' => array_filter([$event->ticket->user]),
                'payload' => [
                    'user_name' => $event->ticket->user?->full_name ?: $event->ticket->user?->name ?: 'Customer',
                    'ticket_number' => $event->ticket->ticket_number,
                    'deep_link' => '/support/'.$event->ticket->id,
                ],
            ]];
        }

        return [[
            'code' => 'SUPPORT_TICKET_REPLIED_AGENT',
            'name' => 'Support Ticket Replied For Agent',
            'subject' => 'Customer replied on ticket {ticket_number}',
            'body' => 'The customer replied on ticket {ticket_number}. Review the latest message to continue the conversation.',
            'channels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'variables' => ['ticket_number'],
            'notification_type' => 'system',
            'related_type' => 'support_ticket',
            'related_id' => $event->ticket->id,
            'users' => array_filter([$event->ticket->assignee]),
            'payload' => [
                'ticket_number' => $event->ticket->ticket_number,
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function supportTicketAssignedDefinitions(SupportTicketAssigned $event): array
    {
        if ($event->ticket->assignee === null) {
            return [];
        }

        return [[
            'code' => 'SUPPORT_TICKET_ASSIGNED',
            'name' => 'Support Ticket Assigned',
            'subject' => 'Ticket {ticket_number} was assigned to you',
            'body' => 'Ticket {ticket_number} is assigned to you. Priority: {ticket_priority}.',
            'channels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'variables' => ['ticket_number', 'ticket_priority'],
            'notification_type' => 'system',
            'related_type' => 'support_ticket',
            'related_id' => $event->ticket->id,
            'users' => array_filter([$event->ticket->assignee]),
            'payload' => [
                'ticket_number' => $event->ticket->ticket_number,
                'ticket_priority' => $event->ticket->priority,
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function supportTicketStatusChangedDefinitions(SupportTicketStatusChanged $event): array
    {
        if (! in_array($event->newStatus, ['resolved', 'closed'], true)) {
            return [];
        }

        return [[
            'code' => 'SUPPORT_TICKET_CLOSED',
            'name' => 'Support Ticket Closed',
            'subject' => 'Ticket {ticket_number} is now {ticket_status}',
            'body' => 'Hello {user_name}, ticket {ticket_number} was marked as {ticket_status}.',
            'channels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'variables' => ['user_name', 'ticket_number', 'ticket_status', 'deep_link'],
            'notification_type' => 'system',
            'related_type' => 'support_ticket',
            'related_id' => $event->ticket->id,
            'users' => array_filter([$event->ticket->user]),
            'payload' => [
                'user_name' => $event->ticket->user?->full_name ?: $event->ticket->user?->name ?: 'Customer',
                'ticket_number' => $event->ticket->ticket_number,
                'ticket_status' => $event->newStatus,
                'deep_link' => '/support/'.$event->ticket->id,
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loyaltyTierChangedDefinitions(LoyaltyTierChanged $event): array
    {
        $history = $event->history;
        $benefitNames = $history->toTier?->benefits
            ?->where('is_active', true)
            ->pluck('name')
            ->filter()
            ->values()
            ->all() ?? [];

        $definitions = [[
            'code' => 'LOYALTY_TIER_CHANGED',
            'name' => 'Loyalty Tier Changed',
            'subject' => 'Your loyalty tier is now {tier_name}',
            'body' => 'Hello {user_name}, your loyalty tier changed from {from_tier} to {tier_name}.',
            'channels' => [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP],
            'variables' => ['user_name', 'from_tier', 'tier_name', 'deep_link'],
            'notification_type' => 'success',
            'related_type' => 'user',
            'related_id' => $history->user_id,
            'users' => array_filter([$history->user]),
            'payload' => [
                'user_name' => $history->user?->full_name ?: $history->user?->name ?: 'Customer',
                'from_tier' => $history->fromTier?->name ?: 'Starter',
                'tier_name' => $history->toTier?->name ?: 'Current Tier',
                'deep_link' => '/loyalty',
            ],
        ]];

        if ($benefitNames !== []) {
            $definitions[] = [
                'code' => 'LOYALTY_BENEFIT_UNLOCKED',
                'name' => 'Loyalty Benefits Unlocked',
                'subject' => 'New loyalty benefits unlocked in {tier_name}',
                'body' => 'Hello {user_name}, your new benefits are: {benefits}.',
                'channels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
                'variables' => ['user_name', 'tier_name', 'benefits', 'deep_link'],
                'notification_type' => 'success',
                'related_type' => 'user',
                'related_id' => $history->user_id,
                'users' => array_filter([$history->user]),
                'payload' => [
                    'user_name' => $history->user?->full_name ?: $history->user?->name ?: 'Customer',
                    'tier_name' => $history->toTier?->name ?: 'Current Tier',
                    'benefits' => implode(', ', $benefitNames),
                    'deep_link' => '/loyalty',
                ],
            ];
        }

        return $definitions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function criticalFinanceAnomalyDefinitions(CriticalFinanceAnomaliesDetected $event): array
    {
        $anomaly = $event->anomalies[0] ?? null;

        if (! is_array($anomaly)) {
            return [];
        }

        $admins = User::query()
            ->where('account_type', User::ACCOUNT_TYPE_ADMIN)
            ->where('is_active', true)
            ->get()
            ->all();

        return [[
            'code' => 'FINANCE_CRITICAL_ANOMALY',
            'name' => 'Finance Critical Anomaly',
            'subject' => 'Critical finance anomaly detected: {anomaly_code}',
            'body' => 'A critical finance anomaly was detected for {entity_reference}. Review the finance dashboard immediately.',
            'channels' => [NotificationChannels::EMAIL, NotificationChannels::IN_APP],
            'variables' => ['anomaly_code', 'entity_reference'],
            'notification_type' => 'payment',
            'related_type' => $anomaly['order']['id'] ?? null ? 'order' : 'finance',
            'related_id' => $anomaly['order']['id'] ?? null,
            'users' => $admins,
            'payload' => [
                'anomaly_code' => $anomaly['code'] ?? 'critical_finance_anomaly',
                'entity_reference' => $anomaly['order']['booking_reference'] ?? ('transaction #'.($anomaly['transaction']['id'] ?? 'unknown')),
            ],
        ]];
    }
}
