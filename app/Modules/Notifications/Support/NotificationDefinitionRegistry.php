<?php

namespace App\Modules\Notifications\Support;

use App\Models\User;
use App\Models\FinancialTransaction;
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

class NotificationDefinitionRegistry
{
    /**
     * Resolve notification definitions for the supplied event.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitionsFor(object $event): array
    {
        return match (true) {
            $event instanceof OrderCreated => [$this->orderCreatedDefinition($event)],
            $event instanceof OrderConfirmed => [$this->orderConfirmedDefinition($event)],
            $event instanceof PaymentSucceeded => [$this->paymentSucceededDefinition($event)],
            $event instanceof RefundIssued => [$this->refundIssuedDefinition($event)],
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
        return [
            'code' => 'ORDER_CREATED',
            'name' => 'Order Created',
            'subject' => 'Order {order_reference} was created',
            'body' => 'Hello {user_name}, your order #{order_reference} was created and is waiting for the next lifecycle step.',
            'channels' => [NotificationChannels::IN_APP],
            'variables' => [
                'user_name',
                'order_id',
                'order_reference',
                'order_status',
            ],
            'notification_type' => 'order',
            'related_type' => 'order',
            'related_id' => $event->order->id,
            'users' => array_filter([$event->order->customer]),
            'payload' => [
                'user_name' => $event->order->customer?->full_name ?: $event->order->customer?->name ?: 'Customer',
                'order_id' => $event->order->id,
                'order_reference' => $event->order->booking_reference ?: ('#'.$event->order->id),
                'order_status' => $event->order->status,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderConfirmedDefinition(OrderConfirmed $event): array
    {
        return [
            'code' => 'ORDER_CONFIRMED',
            'name' => 'Order Confirmed',
            'subject' => 'Order {order_reference} is confirmed',
            'body' => 'Hello {user_name}, your order #{order_reference} is now confirmed and ready for fulfilment.',
            'channels' => [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP],
            'variables' => [
                'user_name',
                'order_id',
                'order_reference',
            ],
            'notification_type' => 'order',
            'related_type' => 'order',
            'related_id' => $event->order->id,
            'users' => array_filter([$event->order->customer]),
            'payload' => [
                'user_name' => $event->order->customer?->full_name ?: $event->order->customer?->name ?: 'Customer',
                'order_id' => $event->order->id,
                'order_reference' => $event->order->booking_reference ?: ('#'.$event->order->id),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSucceededDefinition(PaymentSucceeded $event): array
    {
        return [
            'code' => 'PAYMENT_SUCCEEDED',
            'name' => 'Payment Succeeded',
            'subject' => 'Payment received for order {order_reference}',
            'body' => 'Hello {user_name}, we captured {amount} {currency} successfully for order #{order_reference}.',
            'channels' => [NotificationChannels::SMS, NotificationChannels::IN_APP],
            'variables' => [
                'user_name',
                'order_reference',
                'amount',
                'currency',
            ],
            'notification_type' => 'payment',
            'related_type' => 'order',
            'related_id' => $event->order->id,
            'users' => array_filter([$event->order->customer]),
            'payload' => [
                'user_name' => $event->order->customer?->full_name ?: $event->order->customer?->name ?: 'Customer',
                'order_reference' => $event->order->booking_reference ?: ('#'.$event->order->id),
                'amount' => number_format((float) $event->transaction->amount, 2, '.', ''),
                'currency' => $event->transaction->currency,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function refundIssuedDefinition(RefundIssued $event): array
    {
        $channels = [NotificationChannels::EMAIL, NotificationChannels::IN_APP, NotificationChannels::SMS];

        if (($event->transaction->metadata['source_context'] ?? null) === 'support_ticket') {
            $channels[] = NotificationChannels::WHATSAPP;
        }

        return [
            'code' => 'REFUND_ISSUED',
            'name' => 'Refund Issued',
            'subject' => 'Refund issued for order {order_reference}',
            'body' => 'Hello {user_name}, we issued a refund of {amount} {currency} for order #{order_reference}. Reason: {reason}',
            'channels' => array_values(array_unique($channels)),
            'variables' => [
                'user_name',
                'order_reference',
                'amount',
                'currency',
                'reason',
            ],
            'notification_type' => 'payment',
            'related_type' => 'order',
            'related_id' => $event->order->id,
            'users' => array_filter([$event->order->customer]),
            'payload' => [
                'user_name' => $event->order->customer?->full_name ?: $event->order->customer?->name ?: 'Customer',
                'order_reference' => $event->order->booking_reference ?: ('#'.$event->order->id),
                'amount' => number_format((float) $event->transaction->amount, 2, '.', ''),
                'currency' => $event->transaction->currency,
                'reason' => $event->transaction->reason ?: 'No reason provided.',
            ],
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
            'variables' => ['user_name', 'ticket_number', 'ticket_subject'],
            'notification_type' => 'support',
            'related_type' => 'support_ticket',
            'related_id' => $event->ticket->id,
            'users' => array_filter([$event->ticket->user]),
            'payload' => [
                'user_name' => $event->ticket->user?->full_name ?: $event->ticket->user?->name ?: 'Customer',
                'ticket_number' => $event->ticket->ticket_number,
                'ticket_subject' => $event->ticket->subject,
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
                'notification_type' => 'support',
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
                'variables' => ['user_name', 'ticket_number'],
                'notification_type' => 'support',
                'related_type' => 'support_ticket',
                'related_id' => $event->ticket->id,
                'users' => array_filter([$event->ticket->user]),
                'payload' => [
                    'user_name' => $event->ticket->user?->full_name ?: $event->ticket->user?->name ?: 'Customer',
                    'ticket_number' => $event->ticket->ticket_number,
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
            'notification_type' => 'support',
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
            'notification_type' => 'support',
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
            'variables' => ['user_name', 'ticket_number', 'ticket_status'],
            'notification_type' => 'support',
            'related_type' => 'support_ticket',
            'related_id' => $event->ticket->id,
            'users' => array_filter([$event->ticket->user]),
            'payload' => [
                'user_name' => $event->ticket->user?->full_name ?: $event->ticket->user?->name ?: 'Customer',
                'ticket_number' => $event->ticket->ticket_number,
                'ticket_status' => $event->newStatus,
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
            'channels' => [NotificationChannels::EMAIL, NotificationChannels::IN_APP],
            'variables' => ['user_name', 'from_tier', 'tier_name'],
            'notification_type' => 'loyalty',
            'related_type' => 'user',
            'related_id' => $history->user_id,
            'users' => array_filter([$history->user]),
            'payload' => [
                'user_name' => $history->user?->full_name ?: $history->user?->name ?: 'Customer',
                'from_tier' => $history->fromTier?->name ?: 'Starter',
                'tier_name' => $history->toTier?->name ?: 'Current Tier',
            ],
        ]];

        if ($benefitNames !== []) {
            $definitions[] = [
                'code' => 'LOYALTY_BENEFIT_UNLOCKED',
                'name' => 'Loyalty Benefits Unlocked',
                'subject' => 'New loyalty benefits unlocked in {tier_name}',
                'body' => 'Hello {user_name}, your new benefits are: {benefits}.',
                'channels' => [NotificationChannels::IN_APP],
                'variables' => ['user_name', 'tier_name', 'benefits'],
                'notification_type' => 'loyalty',
                'related_type' => 'user',
                'related_id' => $history->user_id,
                'users' => array_filter([$history->user]),
                'payload' => [
                    'user_name' => $history->user?->full_name ?: $history->user?->name ?: 'Customer',
                    'tier_name' => $history->toTier?->name ?: 'Current Tier',
                    'benefits' => implode(', ', $benefitNames),
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
            'notification_type' => 'finance',
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