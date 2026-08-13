<?php

namespace App\Modules\Notifications\Support;

final class NotificationTemplateCatalog
{
    /**
     * Default templates known to the engine (used by admin sync / seeder).
     *
     * @return list<array{
     *     code: string,
     *     name: string,
     *     subject: ?string,
     *     body: string,
     *     channels: list<string>,
     *     variables: list<string>
     * }>
     */
    public static function definitions(): array
    {
        $orderVars = ['user_name', 'order_id', 'order_reference', 'service_type', 'deep_link'];
        $paymentVars = ['user_name', 'order_id', 'order_reference', 'amount', 'currency', 'service_type', 'deep_link'];

        return [
            [
                'code' => 'ORDER_CREATED',
                'name' => 'Order Created',
                'subject' => 'Your booking {order_reference} was created — complete payment',
                'body' => 'Hello {user_name}, your booking #{order_reference} was created and is waiting for payment.',
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
                'variables' => ['user_name', 'order_id', 'order_reference', 'order_status', 'service_type', 'deep_link'],
            ],
            [
                'code' => 'ORDER_CONFIRMED',
                'name' => 'Order Confirmed',
                'subject' => 'Order {order_reference} is confirmed',
                'body' => 'Hello {user_name}, your order #{order_reference} is now confirmed.',
                'channels' => [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP],
                'variables' => $orderVars,
            ],
            [
                'code' => 'FLIGHT_TICKET_ISSUED',
                'name' => 'Flight Ticket Issued',
                'subject' => 'Your ticket for {order_reference} was issued',
                'body' => 'Hello {user_name}, your flight ticket #{order_reference} has been issued successfully.',
                'channels' => [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP],
                'variables' => $orderVars,
            ],
            [
                'code' => 'HOTEL_BOOKING_CONFIRMED',
                'name' => 'Hotel Booking Confirmed',
                'subject' => 'Your hotel booking {order_reference} is confirmed',
                'body' => 'Hello {user_name}, your hotel booking #{order_reference} is confirmed.',
                'channels' => [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP],
                'variables' => $orderVars,
            ],
            [
                'code' => 'INSURANCE_POLICY_ISSUED',
                'name' => 'Insurance Policy Issued',
                'subject' => 'Your insurance policy {order_reference} was issued',
                'body' => 'Hello {user_name}, your insurance policy #{order_reference} has been issued successfully.',
                'channels' => [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP],
                'variables' => $orderVars,
            ],
            [
                'code' => 'ESIM_ORDER_CONFIRMED',
                'name' => 'eSIM Order Confirmed',
                'subject' => 'Your eSIM order {order_reference} is ready',
                'body' => 'Hello {user_name}, your eSIM order #{order_reference} is confirmed.',
                'channels' => [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP],
                'variables' => $orderVars,
            ],
            [
                'code' => 'PAYMENT_SUCCEEDED',
                'name' => 'Payment Succeeded',
                'subject' => 'Payment received for order {order_reference}',
                'body' => 'Hello {user_name}, we received {amount} {currency} for order #{order_reference}.',
                'channels' => [NotificationChannels::PUSH, NotificationChannels::EMAIL, NotificationChannels::IN_APP, NotificationChannels::SMS],
                'variables' => $paymentVars,
            ],
            [
                'code' => 'PAYMENT_FAILED',
                'name' => 'Payment Failed',
                'subject' => 'Payment failed for order {order_reference}',
                'body' => 'Hello {user_name}, payment for order #{order_reference} failed. Please try again. {reason}',
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL],
                'variables' => ['user_name', 'order_id', 'order_reference', 'reason', 'service_type', 'deep_link'],
            ],
            [
                'code' => 'REFUND_ISSUED',
                'name' => 'Refund Issued',
                'subject' => 'Refund issued for order {order_reference}',
                'body' => 'Hello {user_name}, we issued a refund of {amount} {currency} for order #{order_reference}. Reason: {reason}',
                'channels' => [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::SMS],
                'variables' => ['user_name', 'order_id', 'order_reference', 'amount', 'currency', 'reason', 'service_type', 'deep_link'],
            ],
            [
                'code' => 'FLIGHT_STATUS_UPDATED',
                'name' => 'Flight Status Updated',
                'subject' => 'Update on your flight {order_reference}',
                'body' => 'Hello {user_name}, {summary} Booking #{order_reference}.',
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL],
                'variables' => ['user_name', 'order_id', 'order_reference', 'summary', 'service_type', 'deep_link'],
            ],
            [
                'code' => 'FLIGHT_REMINDER_24H',
                'name' => 'Flight Reminder 24h',
                'subject' => 'Your flight is tomorrow — {order_reference}',
                'body' => 'Hello {user_name}, your flight #{order_reference} departs in about 24 hours.',
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
                'variables' => ['user_name', 'order_id', 'order_reference', 'service_type', 'deep_link', 'reminder_window'],
            ],
            [
                'code' => 'FLIGHT_REMINDER_3H',
                'name' => 'Flight Reminder 3h',
                'subject' => 'Your flight is in 3 hours — {order_reference}',
                'body' => 'Hello {user_name}, your flight #{order_reference} departs in about 3 hours.',
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
                'variables' => ['user_name', 'order_id', 'order_reference', 'service_type', 'deep_link', 'reminder_window'],
            ],
            [
                'code' => 'HOTEL_CHECKIN_REMINDER_24H',
                'name' => 'Hotel Check-in Reminder 24h',
                'subject' => 'Your stay is tomorrow — {order_reference}',
                'body' => 'Hello {user_name}, check-in for hotel booking #{order_reference} is tomorrow.',
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
                'variables' => ['user_name', 'order_id', 'order_reference', 'service_type', 'deep_link', 'reminder_window'],
            ],
            [
                'code' => 'SUPPORT_TICKET_CREATED_CUSTOMER',
                'name' => 'Support Ticket Created For Customer',
                'subject' => 'Support ticket {ticket_number} was created',
                'body' => 'Hello {user_name}, your support ticket {ticket_number} was opened successfully. Our team will update you shortly.',
                'channels' => [NotificationChannels::IN_APP, NotificationChannels::EMAIL],
                'variables' => ['user_name', 'ticket_number', 'ticket_subject', 'deep_link'],
            ],
            [
                'code' => 'SUPPORT_TICKET_REPLIED_CUSTOMER',
                'name' => 'Support Ticket Replied For Customer',
                'subject' => 'New update on ticket {ticket_number}',
                'body' => 'Hello {user_name}, support replied to ticket {ticket_number}.',
                'channels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
                'variables' => ['user_name', 'ticket_number', 'deep_link'],
            ],
            [
                'code' => 'LOYALTY_TIER_CHANGED',
                'name' => 'Loyalty Tier Changed',
                'subject' => 'Your loyalty tier is now {tier_name}',
                'body' => 'Hello {user_name}, your loyalty tier changed from {from_tier} to {tier_name}.',
                'channels' => [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP],
                'variables' => ['user_name', 'from_tier', 'tier_name', 'deep_link'],
            ],
        ];
    }
}
