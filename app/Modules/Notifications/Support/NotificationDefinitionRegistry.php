<?php

namespace App\Modules\Notifications\Support;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\User;
use App\Modules\Admin\Finance\Events\CriticalFinanceAnomaliesDetected;
use App\Modules\Admin\Support\Events\SupportTicketAssigned;
use App\Modules\Admin\Support\Events\SupportTicketCreated;
use App\Modules\Admin\Support\Events\SupportTicketReplied;
use App\Modules\Admin\Support\Events\SupportTicketStatusChanged;
use App\Modules\Loyalty\Events\LoyaltyTierChanged;
use App\Modules\Notifications\Events\AbandonedFlightSearchDue;
use App\Modules\Notifications\Events\PassengerActionDue;
use App\Modules\Notifications\Events\PriceAlertHit;
use App\Modules\Notifications\Services\JourneyOfferResolver;
use App\Modules\Orders\Events\BookingReminderDue;
use App\Modules\Orders\Events\FlightStatusUpdated;
use App\Modules\Orders\Events\HotelStatusUpdated;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderConfirmed;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\PaymentFailed;
use App\Modules\Orders\Events\PaymentSucceeded;
use App\Modules\Orders\Events\RefundFailed;
use App\Modules\Orders\Events\RefundInitiated;
use App\Modules\Orders\Events\RefundIssued;
use Illuminate\Support\Carbon;

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
            $event instanceof RefundInitiated => [$this->refundLifecycleDefinition($event->order, $event->transaction, 'REFUND_INITIATED')],
            $event instanceof RefundFailed => [$this->refundLifecycleDefinition($event->order, $event->transaction, 'REFUND_FAILED', $event->reason)],
            $event instanceof FlightStatusUpdated => [$this->flightStatusUpdatedDefinition($event)],
            $event instanceof HotelStatusUpdated => [$this->hotelStatusUpdatedDefinition($event)],
            $event instanceof OrderCancelled => [$this->orderCancelledDefinition($event)],
            $event instanceof BookingReminderDue => [$this->bookingReminderDefinition($event)],
            $event instanceof SupportTicketCreated => $this->supportTicketCreatedDefinitions($event),
            $event instanceof SupportTicketReplied => $this->supportTicketRepliedDefinitions($event),
            $event instanceof SupportTicketAssigned => $this->supportTicketAssignedDefinitions($event),
            $event instanceof SupportTicketStatusChanged => $this->supportTicketStatusChangedDefinitions($event),
            $event instanceof LoyaltyTierChanged => $this->loyaltyTierChangedDefinitions($event),
            $event instanceof CriticalFinanceAnomaliesDetected => $this->criticalFinanceAnomalyDefinitions($event),
            $event instanceof AbandonedFlightSearchDue => [$this->abandonedFlightSearchDefinition($event)],
            $event instanceof PriceAlertHit => [$this->priceAlertHitDefinition($event)],
            $event instanceof PassengerActionDue => [$this->passengerActionDefinition($event)],
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
            'payload' => array_merge($ctx, $this->journeyMarketing(
                $order,
                $order->customer,
                $order->service_type === Order::SERVICE_TYPE_FLIGHT
                    ? JourneyOfferResolver::STAGE_AFTER_BOOKING
                    : null,
            )),
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
        $paid = $event->order->payment_status === Order::PAYMENT_STATUS_PAID;
        $code = $paid ? 'BOOKING_FAILED' : 'PAYMENT_FAILED';

        return [
            'code' => $code,
            'name' => $paid ? 'Booking Failed' : 'Payment Failed',
            'subject' => $paid
                ? 'We could not confirm booking {order_reference}'
                : 'Payment failed for order {order_reference}',
            'body' => $paid
                ? 'Hello {user_name}, we could not confirm booking #{order_reference}. {reason}'
                : 'Hello {user_name}, payment for order #{order_reference} failed. Please try again. {reason}',
            'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL],
            'variables' => ['user_name', 'order_id', 'order_reference', 'reason', 'service_type', 'deep_link'],
            'notification_type' => $paid ? 'order' : 'payment',
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
        $channels = [NotificationChannels::EMAIL, NotificationChannels::PUSH, NotificationChannels::IN_APP];

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
    private function refundLifecycleDefinition(Order $order, FinancialTransaction $transaction, string $code, ?string $reason = null): array
    {
        $ctx = OrderNotificationContext::base($order);
        $ctx['topic'] = null;
        $amount = number_format((float) $transaction->amount, 2, '.', '');
        $currency = $transaction->currency ?: $order->currency;

        [$name, $subject, $body] = match ($code) {
            'REFUND_INITIATED' => [
                'Refund initiated',
                'We started refunding {amount} {currency}',
                'Hello {user_name}, we started processing a refund of {amount} {currency} for order #{order_reference}.',
            ],
            'REFUND_FAILED' => [
                'Refund failed',
                'We could not complete your refund',
                'Hello {user_name}, we could not complete the refund of {amount} {currency} for order #{order_reference}. {reason}',
            ],
            default => [
                'Refund completed',
                'Refund issued for order {order_reference}',
                'Hello {user_name}, we issued a refund of {amount} {currency} for order #{order_reference}.',
            ],
        };

        return [
            'code' => $code,
            'name' => $name,
            'subject' => $subject,
            'body' => $body,
            'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL],
            'variables' => ['user_name', 'order_id', 'order_reference', 'amount', 'currency', 'reason', 'service_type', 'deep_link'],
            'notification_type' => 'payment',
            'topic' => null,
            'related_type' => 'order',
            'related_id' => $order->id,
            'users' => array_filter([$order->customer]),
            'payload' => array_merge($ctx, [
                'amount' => $amount,
                'currency' => $currency,
                'reason' => $reason ?: ($transaction->reason ?: ''),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderCancelledDefinition(OrderCancelled $event): array
    {
        $order = $event->order;
        $ctx = OrderNotificationContext::journeyPayload($order, OrderNotificationContext::orderDeepLink($order));
        $ctx['topic'] = null;
        $airline = $event->source === OrderCancelled::SOURCE_AIRLINE;
        $isHotel = $order->service_type === Order::SERVICE_TYPE_HOTEL;
        $isFlight = $order->service_type === Order::SERVICE_TYPE_FLIGHT;

        [$code, $subject, $body] = match (true) {
            $isFlight && $airline => [
                'FLIGHT_CANCELLED',
                'Your flight {order_reference} was cancelled',
                '{route} was cancelled by the airline. Open the app to view alternatives or request a refund.',
            ],
            $isHotel => [
                'HOTEL_BOOKING_CANCELLED',
                'Your hotel booking {order_reference} was cancelled',
                'Hello {user_name}, hotel booking #{order_reference} was cancelled.',
            ],
            default => [
                'BOOKING_CANCELLED',
                'Your booking {order_reference} was cancelled',
                'Hello {user_name}, booking #{order_reference} was cancelled successfully.',
            ],
        };

        $origin = OrderNotificationContext::originLabel($order);
        $destination = OrderNotificationContext::destinationLabel($order);
        $departure = OrderNotificationContext::departureTime($order);

        return [
            'code' => $code,
            'name' => 'Booking cancelled',
            'subject' => $subject,
            'body' => $body,
            'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL],
            'variables' => ['user_name', 'order_id', 'order_reference', 'route', 'origin', 'destination', 'reason', 'deep_link'],
            'notification_type' => $isFlight ? 'flight' : ($isHotel ? 'order' : 'order'),
            'topic' => null,
            'related_type' => 'order',
            'related_id' => $order->id,
            'users' => array_filter([$order->customer]),
            'payload' => array_merge($ctx, [
                'reason' => $event->reason ?: '',
                'cancel_source' => $event->source,
                'journey_card' => true,
                'alternatives_deep_link' => '/flights?origin='.rawurlencode($origin).'&destination='.rawurlencode($destination).($departure ? '&date='.$departure->toDateString() : ''),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hotelStatusUpdatedDefinition(HotelStatusUpdated $event): array
    {
        $order = $event->order;
        $ctx = OrderNotificationContext::base($order);
        $changes = $event->changes;

        [$code, $subject, $body, $key] = match (true) {
            isset($changes['check_in']) => [
                'HOTEL_CHECKIN_CHANGED',
                'Your hotel check-in date was updated',
                'Check-in for #{order_reference} changed from {from_value} to {to_value}.',
                'check_in',
            ],
            isset($changes['check_out']) => [
                'HOTEL_CHECKOUT_CHANGED',
                'Your hotel check-out date was updated',
                'Check-out for #{order_reference} changed from {from_value} to {to_value}.',
                'check_out',
            ],
            default => [
                'HOTEL_BOOKING_MODIFIED',
                'Your hotel booking was updated',
                'Hello {user_name}, hotel booking #{order_reference} was updated. {summary}',
                array_key_first($changes) ?: 'status',
            ],
        };

        return [
            'code' => $code,
            'name' => 'Hotel booking updated',
            'subject' => $subject,
            'body' => $body,
            'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            'variables' => ['user_name', 'order_id', 'order_reference', 'from_value', 'to_value', 'summary', 'deep_link'],
            'notification_type' => 'order',
            'topic' => null,
            'related_type' => 'order',
            'related_id' => $order->id,
            'users' => array_filter([$order->customer]),
            'payload' => array_merge($ctx, [
                'summary' => $event->summary ?: 'Your hotel booking was updated.',
                'from_value' => $this->stringifyChangeValue(data_get($changes, $key.'.from')),
                'to_value' => $this->stringifyChangeValue(data_get($changes, $key.'.to')),
                'changes' => $changes,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function flightStatusUpdatedDefinition(FlightStatusUpdated $event): array
    {
        $order = $event->order;
        $ctx = OrderNotificationContext::journeyPayload($order, OrderNotificationContext::orderDeepLink($order));
        $resolved = $this->resolveFlightChange($event->changes, $event->summary);
        $ctx['topic'] = null;

        return [
            'code' => $resolved['code'],
            'name' => $resolved['name'],
            'subject' => $resolved['subject'],
            'body' => $resolved['body'],
            'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL],
            'variables' => ['user_name', 'order_id', 'order_reference', 'summary', 'from_value', 'to_value', 'route', 'origin', 'destination', 'service_type', 'deep_link'],
            'notification_type' => 'flight',
            'topic' => null,
            'related_type' => 'order',
            'related_id' => $order->id,
            'users' => array_filter([$order->customer]),
            'payload' => array_merge($ctx, $resolved['payload'], [
                'changes' => $event->changes,
                'journey_card' => true,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array{code: string, name: string, subject: string, body: string, payload: array<string, mixed>}
     */
    private function resolveFlightChange(array $changes, ?string $summary): array
    {
        $statusTo = strtolower((string) data_get($changes, 'flight_status.to', data_get($changes, 'status.to', '')));

        if (str_contains($statusTo, 'cancel')) {
            return $this->flightChangeMeta(
                'FLIGHT_CANCELLED',
                'Flight cancelled',
                'Your flight {order_reference} was cancelled',
                '{route} on {departure_date} was cancelled by the airline.',
                $changes,
                $summary ?: 'Your flight was cancelled.',
                'flight_status',
            );
        }

        if (str_contains($statusTo, 'delay') || isset($changes['delayed'])) {
            return $this->flightChangeMeta(
                'FLIGHT_DELAYED',
                'Flight delayed',
                'Your flight {order_reference} is delayed',
                'Your flight {route} is delayed. New time: {to_value}.',
                $changes,
                $summary ?: 'Your flight is delayed.',
                isset($changes['departure_time']) ? 'departure_time' : 'flight_status',
            );
        }

        $boarding = strtolower((string) data_get($changes, 'boarding_status.to', ''));

        if (str_contains($boarding, 'final')) {
            return $this->flightChangeMeta('BOARDING_FINAL_CALL', 'Boarding final call', 'Final call for {route}', 'This is the final call for {route}. Go to the gate now.', $changes, $summary ?: 'Final boarding call.', 'boarding_status');
        }

        if (str_contains($boarding, 'close')) {
            return $this->flightChangeMeta('BOARDING_CLOSED', 'Boarding closed', 'Boarding is closed', 'Boarding for {route} is closed.', $changes, $summary ?: 'Boarding is closed.', 'boarding_status');
        }

        if (str_contains($boarding, 'board') || str_contains($boarding, 'start')) {
            return $this->flightChangeMeta('BOARDING_STARTED', 'Boarding started', 'Boarding has started', 'Boarding for {route} has started. Go to gate {to_value}.', $changes, $summary ?: 'Boarding has started.', isset($changes['gate']) ? 'gate' : 'boarding_status');
        }

        if (isset($changes['boarding_pass_url'])) {
            return $this->flightChangeMeta('BOARDING_PASS_AVAILABLE', 'Boarding pass available', 'Your boarding pass is ready', 'Open your boarding pass for {route}.', $changes, $summary ?: 'Your boarding pass is ready.', 'boarding_pass_url');
        }

        if (isset($changes['gate'])) {
            $fromGate = data_get($changes, 'gate.from');
            $assigned = $fromGate === null || $fromGate === '';

            return $this->flightChangeMeta(
                $assigned ? 'GATE_ASSIGNED' : 'FLIGHT_GATE_CHANGED',
                $assigned ? 'Gate assigned' : 'Gate changed',
                $assigned ? 'Your gate is {to_value}' : 'Your departure gate was updated',
                $assigned ? 'Go to gate {to_value} for {route}.' : 'Gate changed from {from_value} to {to_value} for {route}.',
                $changes,
                $summary ?: 'Your gate information was updated.',
                'gate',
            );
        }

        if (isset($changes['departure_time'])) {
            return $this->flightChangeMeta(
                'FLIGHT_TIME_CHANGED',
                'Departure time changed',
                'Your departure time was updated',
                'Departure for {route} changed from {from_value} to {to_value}.',
                $changes,
                $summary ?: 'Your departure time was updated.',
                'departure_time',
            );
        }

        if (isset($changes['arrival_time'])) {
            return $this->flightChangeMeta(
                'FLIGHT_ARRIVAL_CHANGED',
                'Arrival time changed',
                'Your arrival time was updated',
                'Arrival for {route} changed from {from_value} to {to_value}.',
                $changes,
                $summary ?: 'Your arrival time was updated.',
                'arrival_time',
            );
        }

        if (isset($changes['terminal'])) {
            return $this->flightChangeMeta(
                'FLIGHT_TERMINAL_CHANGED',
                'Terminal changed',
                'Your departure terminal was updated',
                'Terminal changed from {from_value} to {to_value} for {route}.',
                $changes,
                $summary ?: 'Your terminal was updated.',
                'terminal',
            );
        }

        if (isset($changes['seat'])) {
            $fromSeat = data_get($changes, 'seat.from');
            $assigned = $fromSeat === null || $fromSeat === '';

            return $this->flightChangeMeta(
                $assigned ? 'SEAT_ASSIGNED' : 'SEAT_CHANGED',
                $assigned ? 'Seat assigned' : 'Seat changed',
                $assigned ? 'Your seat is {to_value}' : 'Your seat was updated',
                $assigned ? 'Seat {to_value} was assigned for {route}.' : 'Your seat changed from {from_value} to {to_value}.',
                $changes,
                $summary ?: 'Your seat was updated.',
                'seat',
            );
        }

        if (isset($changes['cabin_class']) || isset($changes['class'])) {
            $key = isset($changes['cabin_class']) ? 'cabin_class' : 'class';

            return $this->flightChangeMeta(
                'FLIGHT_CLASS_CHANGED',
                'Travel class updated',
                'Your travel class was updated',
                'Your class changed from {from_value} to {to_value}.',
                $changes,
                $summary ?: 'Your travel class was updated.',
                $key,
            );
        }

        if (isset($changes['origin']) || isset($changes['destination']) || isset($changes['flight_number'])) {
            $key = isset($changes['flight_number']) ? 'flight_number' : (isset($changes['destination']) ? 'destination' : 'origin');

            return $this->flightChangeMeta(
                'FLIGHT_CHANGED',
                'Flight details updated',
                'Your flight details were updated',
                'Your flight {route} was updated: {from_value} → {to_value}.',
                $changes,
                $summary ?: 'Your flight details were updated.',
                $key,
            );
        }

        return $this->flightChangeMeta(
            'FLIGHT_STATUS_UPDATED',
            'Flight status updated',
            'Update on your flight {order_reference}',
            'Hello {user_name}, {summary} Booking #{order_reference}.',
            $changes,
            $summary ?: 'There is an update on your flight.',
            array_key_first($changes) ?: 'status',
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array{code: string, name: string, subject: string, body: string, payload: array<string, mixed>}
     */
    private function flightChangeMeta(
        string $code,
        string $name,
        string $subject,
        string $body,
        array $changes,
        string $summary,
        string $key,
    ): array {
        $from = data_get($changes, $key.'.from');
        $to = data_get($changes, $key.'.to');

        return [
            'code' => $code,
            'name' => $name,
            'subject' => $subject,
            'body' => $body,
            'payload' => [
                'summary' => $summary,
                'from_value' => $this->stringifyChangeValue($from),
                'to_value' => $this->stringifyChangeValue($to),
                'change_key' => $key,
            ],
        ];
    }

    private function stringifyChangeValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T?/', $value) === 1) {
            try {
                return OrderNotificationContext::formatClock(Carbon::parse($value)) ?: $value;
            } catch (\Throwable) {
                return $value;
            }
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingReminderDefinition(BookingReminderDue $event): array
    {
        $order = $event->order->loadMissing('customer');
        $customer = $order->customer;
        $orderLink = OrderNotificationContext::orderDeepLink($order);
        $destination = OrderNotificationContext::destinationLabel($order);
        $city = OrderNotificationContext::destinationCitySlug($order);
        $offers = [];

        $meta = match ($event->window) {
            BookingReminderDue::WINDOW_3H => [
                'code' => 'FLIGHT_REMINDER_3H',
                'subject' => 'Your flight to {destination} is in 3 hours',
                'body' => '{route} departs at {departure_clock}. Head to the airport now. Booking {order_reference}.',
                'type' => 'flight',
                'topic' => NotificationTopics::BOOKING_REMINDERS,
                'deep_link' => $orderLink,
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_1H => [
                'code' => 'FLIGHT_REMINDER_1H',
                'subject' => 'Your flight is in 1 hour',
                'body' => 'Time to head to the airport. {route} departs at {departure_clock}.',
                'type' => 'flight',
                'topic' => NotificationTopics::BOOKING_REMINDERS,
                'deep_link' => $orderLink,
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_HOTEL_CHECKIN_24H => [
                'code' => 'HOTEL_CHECKIN_REMINDER_24H',
                'subject' => 'Your stay is tomorrow — {order_reference}',
                'body' => 'Hello {user_name}, check-in for hotel booking #{order_reference} is tomorrow.',
                'type' => 'order',
                'topic' => NotificationTopics::BOOKING_REMINDERS,
                'deep_link' => $orderLink,
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL],
            ],
            BookingReminderDue::WINDOW_ARRIVAL => [
                'code' => 'DESTINATION_ARRIVAL',
                'subject' => 'Welcome to {destination}',
                'body' => 'You have arrived in {destination}. Open the app if you need a hotel nearby.',
                'type' => 'flight',
                'topic' => NotificationTopics::BOOKING_REMINDERS,
                'deep_link' => $orderLink,
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_OFFER_ESIM => [
                'code' => 'OFFER_ESIM',
                'subject' => 'Need internet in {destination}?',
                'body' => 'Activate an eSIM before you fly and stay connected from the moment you land.',
                'type' => 'tag',
                'topic' => NotificationTopics::BOOKING_REMINDERS,
                'deep_link' => '/esim?country='.(OrderNotificationContext::destinationCountry($order) ?: 'TN'),
                'channels' => [NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_OFFER_INSURANCE => [
                'code' => 'OFFER_INSURANCE',
                'subject' => 'Protect your trip to {destination}',
                'body' => 'Add travel insurance before your departure.',
                'type' => 'tag',
                'topic' => NotificationTopics::INSURANCE,
                'deep_link' => '/insurance?order_id='.$order->id,
                'channels' => [NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_OFFER_HOTELS => [
                'code' => 'OFFER_HOTELS_AT_DESTINATION',
                'subject' => 'Need a hotel in {destination}?',
                'body' => 'Discover hotels in {destination} and book directly.',
                'type' => 'tag',
                'topic' => NotificationTopics::HOTEL,
                'deep_link' => '/hotels?city='.rawurlencode($city),
                'channels' => [NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_OFFER_CARS => [
                'code' => 'OFFER_CARS_AT_DESTINATION',
                'subject' => 'Need a car in {destination}?',
                'body' => 'You have landed in {destination}. Rent a car for your stay — booking {order_reference}.',
                'type' => 'tag',
                'topic' => NotificationTopics::CAR_RENTAL,
                'deep_link' => '/cars?city='.rawurlencode($city),
                'channels' => [NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_PAYMENT_NUDGE => [
                'code' => 'PAYMENT_REMINDER',
                'subject' => 'Complete payment for {order_reference}',
                'body' => 'Hello {user_name}, your booking #{order_reference} is still waiting for payment. Complete it to confirm your trip.',
                'type' => 'payment',
                'topic' => null,
                'deep_link' => $orderLink,
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_PAYMENT_EXPIRED => [
                'code' => 'PAYMENT_EXPIRED',
                'subject' => 'Payment window expired for {order_reference}',
                'body' => 'Hello {user_name}, the payment window for booking #{order_reference} has expired.',
                'type' => 'payment',
                'topic' => null,
                'deep_link' => $orderLink,
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_HOTEL_CANCEL_DEADLINE => [
                'code' => 'HOTEL_CANCELLATION_DEADLINE_REMINDER',
                'subject' => 'Last chance to cancel {order_reference} for free',
                'body' => 'You can still cancel hotel booking #{order_reference} until {deadline} without fees.',
                'type' => 'order',
                'topic' => NotificationTopics::BOOKING_REMINDERS,
                'deep_link' => $orderLink,
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_CHECKIN_OPEN => [
                'code' => 'ONLINE_CHECKIN_OPEN',
                'subject' => 'Check-in is open for {destination}',
                'body' => 'You can now complete check-in and get your boarding pass for {route}.',
                'type' => 'flight',
                'topic' => NotificationTopics::BOOKING_REMINDERS,
                'deep_link' => $orderLink,
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_HOTEL_CHECKOUT => [
                'code' => 'HOTEL_CHECKOUT_REMINDER',
                'subject' => 'Check-out is tomorrow',
                'body' => 'Check-out for hotel booking #{order_reference} is tomorrow.',
                'type' => 'order',
                'topic' => NotificationTopics::BOOKING_REMINDERS,
                'deep_link' => $orderLink,
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_ESIM_ACTIVATION => [
                'code' => 'ESIM_ACTIVATION_REMINDER',
                'subject' => 'Activate your eSIM for {destination}',
                'body' => 'Activate your eSIM so you stay online when you land in {destination}.',
                'type' => 'order',
                'topic' => NotificationTopics::BOOKING_REMINDERS,
                'deep_link' => '/esim',
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            ],
            BookingReminderDue::WINDOW_POST_TRIP => [
                'code' => 'POST_TRIP_THANKS',
                'subject' => 'How was {destination}?',
                'body' => 'Hello {user_name}, welcome back from {destination}. Open the app to collect points or book your next trip.',
                'type' => 'success',
                'topic' => NotificationTopics::BOOKING_REMINDERS,
                'deep_link' => '/loyalty',
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            ],
            default => [
                'code' => 'FLIGHT_REMINDER_24H',
                'subject' => 'Your flight to {destination} is tomorrow',
                'body' => 'Hello {user_name}, {route} departs tomorrow at {departure_clock}. {checklist_hint}',
                'type' => 'flight',
                'topic' => NotificationTopics::BOOKING_REMINDERS,
                'deep_link' => $orderLink,
                'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL],
            ],
        };

        $marketing = ['offers' => $offers, 'next_best_offer' => null, 'checklist' => [], 'missing_labels' => '', 'checklist_hint' => ''];

        if ($customer && in_array($event->window, [BookingReminderDue::WINDOW_3H, BookingReminderDue::WINDOW_24H], true)) {
            $marketing = $this->journeyMarketing($order, $customer, JourneyOfferResolver::STAGE_BEFORE_DEPARTURE);
            $offers = $marketing['offers'];
        }

        if ($customer && $event->window === BookingReminderDue::WINDOW_ARRIVAL) {
            $marketing = $this->journeyMarketing($order, $customer, JourneyOfferResolver::STAGE_DURING_JOURNEY);
            $offers = $marketing['offers'];
            if (is_array($marketing['next_best_offer'] ?? null)) {
                $meta['deep_link'] = $marketing['next_best_offer']['deep_link'];
            }
        }

        if ($customer && $event->window === BookingReminderDue::WINDOW_POST_TRIP) {
            $marketing = $this->journeyMarketing($order, $customer, JourneyOfferResolver::STAGE_AFTER_JOURNEY);
            $offers = $marketing['offers'];
        }

        $ctx = OrderNotificationContext::journeyPayload($order, $meta['deep_link']);

        return [
            'code' => $meta['code'],
            'name' => 'Journey notification',
            'subject' => $meta['subject'],
            'body' => $meta['body'],
            'channels' => $meta['channels'],
            'variables' => ['user_name', 'order_id', 'order_reference', 'origin', 'destination', 'route', 'departure_clock', 'arrival_clock', 'deep_link', 'reminder_window', 'checklist_hint', 'missing_labels'],
            'notification_type' => $meta['type'],
            'topic' => $meta['topic'],
            'related_type' => 'order',
            'related_id' => $order->id,
            'users' => array_filter([$customer]),
            'payload' => array_merge($ctx, $marketing, [
                'reminder_window' => $event->window,
                'destination' => $destination,
                'deadline' => OrderNotificationContext::cancellationDeadline($order)?->timezone(config('app.timezone'))->format('H:i') ?: '23:59',
                'offers' => $offers,
                'journey_card' => true,
                'idempotency_key' => $order->id.'|'.$meta['code'],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function journeyMarketing(Order $order, ?User $customer, ?string $stage): array
    {
        $empty = [
            'stage' => $stage,
            'offers' => [],
            'next_best_offer' => null,
            'checklist' => [],
            'missing_labels' => '',
            'missing_labels_ar' => '',
            'checklist_hint' => '',
            'checklist_hint_ar' => '',
        ];

        if ($customer === null || $stage === null) {
            return $empty;
        }

        $recommendation = app(JourneyOfferResolver::class)->recommend($order, $customer, $stage);
        $missing = (string) ($recommendation['missing_labels'] ?? '');
        $missingAr = (string) ($recommendation['missing_labels_ar'] ?? '');

        return array_merge($recommendation, [
            'checklist_hint' => $missing !== ''
                ? 'Almost ready — still missing: '.$missing
                : 'Your trip checklist is complete.',
            'checklist_hint_ar' => $missingAr !== ''
                ? 'كل شيء جاهز تقريباً! بقي فقط: '.$missingAr
                : 'قائمة رحلتك مكتملة.',
        ]);
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

    /**
     * @return array<string, mixed>
     */
    private function abandonedFlightSearchDefinition(AbandonedFlightSearchDue $event): array
    {
        $intent = $event->intent->loadMissing('user');
        $payload = $intent->notificationPayload();

        return [
            'code' => 'ABANDONED_FLIGHT_SEARCH',
            'name' => 'Abandoned flight search',
            'subject' => 'Your trip to {destination} is still available',
            'body' => 'Fares to {destination} start from {price} {currency}.',
            'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            'variables' => ['user_name', 'origin', 'destination', 'route', 'departure_date', 'price', 'currency', 'deep_link'],
            'notification_type' => 'tag',
            'topic' => null,
            'related_type' => 'travel_search_intent',
            'related_id' => $intent->id,
            'users' => array_filter([$intent->user]),
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function priceAlertHitDefinition(PriceAlertHit $event): array
    {
        $alert = $event->alert->loadMissing('user');
        $payload = $alert->notificationPayload($event->currentPrice);

        return [
            'code' => 'PRICE_ALERT_HIT',
            'name' => 'Price alert hit',
            'subject' => 'Your watched fare to {destination} is now {price} {currency}',
            'body' => 'The trip you are watching is now within your budget of {target_price} {currency}.',
            'channels' => [NotificationChannels::PUSH, NotificationChannels::IN_APP],
            'variables' => ['user_name', 'origin', 'destination', 'route', 'departure_date', 'price', 'target_price', 'currency', 'deep_link'],
            'notification_type' => 'tag',
            'topic' => null,
            'related_type' => 'price_alert',
            'related_id' => $alert->id,
            'users' => array_filter([$alert->user]),
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function passengerActionDefinition(PassengerActionDue $event): array
    {
        $catalog = NotificationActionCatalog::find($event->code) ?? [];
        $payload = array_merge([
            'user_name' => $event->user->full_name ?: $event->user->name ?: 'Customer',
            'deep_link' => '/my-orders',
        ], $event->payload);

        $relatedId = $event->relatedId;
        if (! is_numeric($relatedId)) {
            $relatedId = null;
        }

        $family = NotificationInboxContract::family($event->code);
        $topic = match (true) {
            in_array($event->code, ['LOGIN_ALERT', 'NEW_DEVICE_LOGIN'], true) => NotificationTopics::LOGIN_ALERTS,
            $family === NotificationInboxContract::FAMILY_JOURNEY => NotificationTopics::BOOKING_REMINDERS,
            $family === NotificationInboxContract::FAMILY_MARKETING => NotificationTopics::PROMOTIONAL,
            default => null,
        };

        return [
            'code' => $event->code,
            'name' => $catalog['name'] ?? $event->code,
            'subject' => $catalog['subject'] ?? '{user_name}',
            'body' => $catalog['body'] ?? '',
            'channels' => $event->channels !== []
                ? $event->channels
                : ($catalog['channels'] ?? [NotificationChannels::PUSH, NotificationChannels::IN_APP]),
            'variables' => $catalog['variables'] ?? array_keys($payload),
            'notification_type' => NotificationInboxContract::category($event->code, $payload) === NotificationInboxContract::CATEGORY_SECURITY
                ? 'system'
                : 'order',
            'topic' => $topic,
            'related_type' => $event->relatedType,
            'related_id' => $relatedId !== null ? (int) $relatedId : null,
            'users' => [$event->user],
            'payload' => $payload,
        ];
    }
}
