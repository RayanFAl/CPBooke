<?php

namespace App\Modules\Notifications\Services;

use App\Models\Order;
use App\Models\SavedPassenger;
use App\Models\User;
use App\Models\UserNotification;
use App\Modules\Notifications\Events\PassengerActionDue;
use App\Modules\Notifications\Support\NotificationTopics;
use App\Modules\Notifications\Support\OrderNotificationContext;
use App\Modules\Orders\Events\BookingReminderDue;
use Illuminate\Support\Carbon;

class JourneyCampaignDispatcher
{
    public function __construct(
        private readonly NotificationPreferenceResolver $preferenceResolver,
        private readonly TravelMarketingService $travelMarketingService,
        private readonly JourneyOfferResolver $journeyOfferResolver,
    ) {}

    public function dispatch(Carbon $now): void
    {
        $this->dispatchFlightReminder($now, BookingReminderDue::WINDOW_24H, 'FLIGHT_REMINDER_24H', 23, 25);
        $this->dispatchFlightReminder($now, BookingReminderDue::WINDOW_3H, 'FLIGHT_REMINDER_3H', 2.5, 3.5);
        $this->dispatchFlightReminder($now, BookingReminderDue::WINDOW_1H, 'FLIGHT_REMINDER_1H', 0.75, 1.25);
        $this->dispatchFlightReminder($now, BookingReminderDue::WINDOW_CHECKIN_OPEN, 'ONLINE_CHECKIN_OPEN', 46, 50);
        $this->dispatchArrival($now);
        $this->dispatchPostTrip($now);
        $this->dispatchHotelCheckInReminders($now);
        $this->dispatchHotelCheckOutReminders($now);
        $this->dispatchHotelCancellationDeadlines($now);
        $this->dispatchEsimActivationReminders($now);
        $this->dispatchPassportExpiryReminders($now);
        $this->dispatchPaymentNudges($now);
        $this->dispatchPaymentExpired($now);
        $this->travelMarketingService->dispatchDue($now);
    }

    private function dispatchFlightReminder(
        Carbon $now,
        string $window,
        string $templateCode,
        float $fromHours,
        float $toHours,
    ): void {
        $this->eachFlight($now, $fromHours, $toHours, function (Order $order, User $customer) use ($window, $templateCode): void {
            if (! $this->preferenceResolver->topicEnabled($customer, NotificationTopics::BOOKING_REMINDERS)) {
                return;
            }

            if ($this->alreadySent($order->id, $templateCode)) {
                return;
            }

            event(new BookingReminderDue($order, $window));
        });
    }

    private function dispatchArrival(Carbon $now): void
    {
        $this->eachFlightByArrival($now, -0.25, 1.5, function (Order $order, User $customer): void {
            if (! $this->preferenceResolver->topicEnabled($customer, NotificationTopics::BOOKING_REMINDERS)) {
                return;
            }

            if ($this->alreadySent($order->id, 'DESTINATION_ARRIVAL')) {
                return;
            }

            event(new BookingReminderDue($order, BookingReminderDue::WINDOW_ARRIVAL));
        });
    }

    private function dispatchPostTrip(Carbon $now): void
    {
        $this->eachFlightByArrival($now, 70, 76, function (Order $order, User $customer): void {
            if (! $this->preferenceResolver->topicEnabled($customer, NotificationTopics::BOOKING_REMINDERS)) {
                return;
            }

            if ($this->alreadySent($order->id, 'POST_TRIP_THANKS')) {
                return;
            }

            event(new BookingReminderDue($order, BookingReminderDue::WINDOW_POST_TRIP));
        });
    }

    private function dispatchHotelCheckInReminders(Carbon $now): void
    {
        $targetDay = $now->copy()->addDay()->toDateString();

        Order::query()
            ->with('customer')
            ->where('service_type', Order::SERVICE_TYPE_HOTEL)
            ->whereIn('status', [
                Order::STATUS_CONFIRMED,
                Order::STATUS_COMPLETED,
            ])
            ->whereNotNull('details')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($targetDay): void {
                foreach ($orders as $order) {
                    $checkIn = OrderNotificationContext::checkInDate($order);
                    $customer = $order->customer;

                    if ($checkIn === null || $checkIn->toDateString() !== $targetDay || $customer === null) {
                        continue;
                    }

                    if (
                        ! $this->preferenceResolver->topicEnabled($customer, NotificationTopics::BOOKING_REMINDERS)
                        || ! $this->preferenceResolver->topicEnabled($customer, NotificationTopics::HOTEL)
                    ) {
                        continue;
                    }

                    if ($this->alreadySent($order->id, 'HOTEL_CHECKIN_REMINDER_24H')) {
                        continue;
                    }

                    event(new BookingReminderDue($order, BookingReminderDue::WINDOW_HOTEL_CHECKIN_24H));
                }
            });
    }

    private function dispatchHotelCancellationDeadlines(Carbon $now): void
    {
        Order::query()
            ->with('customer')
            ->where('service_type', Order::SERVICE_TYPE_HOTEL)
            ->whereIn('status', [
                Order::STATUS_CONFIRMED,
                Order::STATUS_COMPLETED,
            ])
            ->whereNotNull('details')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($now): void {
                foreach ($orders as $order) {
                    $deadline = OrderNotificationContext::cancellationDeadline($order);
                    $customer = $order->customer;

                    if ($deadline === null || $customer === null) {
                        continue;
                    }

                    if ($deadline->lt($now) || $deadline->gt($now->copy()->addHours(18))) {
                        continue;
                    }

                    if (! $this->preferenceResolver->topicEnabled($customer, NotificationTopics::BOOKING_REMINDERS)) {
                        continue;
                    }

                    if ($this->alreadySent($order->id, 'HOTEL_CANCELLATION_DEADLINE_REMINDER')) {
                        continue;
                    }

                    event(new BookingReminderDue($order, BookingReminderDue::WINDOW_HOTEL_CANCEL_DEADLINE));
                }
            });
    }

    private function dispatchHotelCheckOutReminders(Carbon $now): void
    {
        $targetDay = $now->copy()->addDay()->toDateString();

        Order::query()
            ->with('customer')
            ->where('service_type', Order::SERVICE_TYPE_HOTEL)
            ->whereIn('status', [
                Order::STATUS_CONFIRMED,
                Order::STATUS_COMPLETED,
            ])
            ->whereNotNull('details')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($targetDay): void {
                foreach ($orders as $order) {
                    $checkOut = OrderNotificationContext::checkOutDate($order);
                    $customer = $order->customer;

                    if ($checkOut === null || $checkOut->toDateString() !== $targetDay || $customer === null) {
                        continue;
                    }

                    if (
                        ! $this->preferenceResolver->topicEnabled($customer, NotificationTopics::BOOKING_REMINDERS)
                        || ! $this->preferenceResolver->topicEnabled($customer, NotificationTopics::HOTEL)
                    ) {
                        continue;
                    }

                    if ($this->alreadySent($order->id, 'HOTEL_CHECKOUT_REMINDER')) {
                        continue;
                    }

                    event(new BookingReminderDue($order, BookingReminderDue::WINDOW_HOTEL_CHECKOUT));
                }
            });
    }

    private function dispatchEsimActivationReminders(Carbon $now): void
    {
        $this->eachFlight($now, 10, 14, function (Order $order, User $customer): void {
            if (! $this->preferenceResolver->topicEnabled($customer, NotificationTopics::BOOKING_REMINDERS)) {
                return;
            }

            $country = OrderNotificationContext::destinationCountry($order);

            if ($country === null || ! $this->journeyOfferResolver->hasActiveEsimForCountry($customer, $country)) {
                return;
            }

            if ($this->alreadySent($order->id, 'ESIM_ACTIVATION_REMINDER')) {
                return;
            }

            event(new BookingReminderDue($order, BookingReminderDue::WINDOW_ESIM_ACTIVATION));
        });
    }

    private function dispatchPassportExpiryReminders(Carbon $now): void
    {
        SavedPassenger::query()
            ->with('user')
            ->whereNotNull('passport_expiry')
            ->whereBetween('passport_expiry', [
                $now->copy()->addDays(14)->startOfDay(),
                $now->copy()->addDays(30)->endOfDay(),
            ])
            ->orderBy('id')
            ->chunkById(100, function ($passengers): void {
                foreach ($passengers as $passenger) {
                    /** @var SavedPassenger $passenger */
                    $user = $passenger->user;

                    if ($user === null || ! $this->preferenceResolver->topicEnabled($user, NotificationTopics::BOOKING_REMINDERS)) {
                        continue;
                    }

                    $already = UserNotification::query()
                        ->where('user_id', $user->id)
                        ->where('template_code', 'PASSPORT_EXPIRY_REMINDER')
                        ->where('data->variables->passenger_id', $passenger->id)
                        ->exists();

                    if ($already) {
                        continue;
                    }

                    $destination = $this->nextFlightDestination($user);

                    event(new PassengerActionDue(
                        $user,
                        'PASSPORT_EXPIRY_REMINDER',
                        [
                            'passenger_id' => $passenger->id,
                            'expiry_date' => $passenger->passport_expiry?->toDateString(),
                            'destination' => $destination,
                            'deep_link' => '/profile/passengers',
                        ],
                        'user',
                        $user->id,
                    ));
                }
            });
    }

    private function nextFlightDestination(User $user): string
    {
        $from = now()->subHours(6);
        $until = now()->addDays(90);
        $best = null;
        $bestDeparture = null;

        Order::query()
            ->where('customer_id', $user->id)
            ->where('service_type', Order::SERVICE_TYPE_FLIGHT)
            ->whereIn('status', [
                Order::STATUS_CONFIRMED,
                Order::STATUS_TICKETED,
                Order::STATUS_COMPLETED,
            ])
            ->whereNotNull('details')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->each(function (Order $order) use ($from, $until, &$best, &$bestDeparture): void {
                $departure = OrderNotificationContext::departureTime($order);

                if ($departure === null || $departure->lt($from) || $departure->gt($until)) {
                    return;
                }

                if ($bestDeparture === null || $departure->lt($bestDeparture)) {
                    $best = $order;
                    $bestDeparture = $departure;
                }
            });

        if (! $best instanceof Order) {
            return 'your next trip';
        }

        return OrderNotificationContext::destinationLabel($best) ?: 'your next trip';
    }

    private function dispatchPaymentNudges(Carbon $now): void
    {
        Order::query()
            ->with('customer')
            ->whereIn('status', [
                Order::STATUS_PENDING_PAYMENT,
                Order::STATUS_DRAFT,
            ])
            ->where('payment_status', Order::PAYMENT_STATUS_UNPAID)
            ->whereBetween('created_at', [
                $now->copy()->subHours(3),
                $now->copy()->subMinutes(45),
            ])
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $customer = $order->customer;

                    if ($customer === null || $this->alreadySent($order->id, 'PAYMENT_REMINDER')) {
                        continue;
                    }

                    event(new BookingReminderDue($order, BookingReminderDue::WINDOW_PAYMENT_NUDGE));
                }
            });
    }

    private function dispatchPaymentExpired(Carbon $now): void
    {
        Order::query()
            ->with('customer')
            ->whereIn('status', [
                Order::STATUS_PENDING_PAYMENT,
                Order::STATUS_DRAFT,
            ])
            ->where('payment_status', Order::PAYMENT_STATUS_UNPAID)
            ->where('created_at', '<=', $now->copy()->subHours(3))
            ->where('created_at', '>=', $now->copy()->subDays(2))
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $customer = $order->customer;

                    if ($customer === null || $this->alreadySent($order->id, 'PAYMENT_EXPIRED')) {
                        continue;
                    }

                    event(new BookingReminderDue($order, BookingReminderDue::WINDOW_PAYMENT_EXPIRED));
                }
            });
    }

    /**
     * @param  callable(Order, User): void  $callback
     */
    private function eachFlight(Carbon $now, float $fromHours, float $toHours, callable $callback): void
    {
        $from = $now->copy()->addMinutes((int) round($fromHours * 60));
        $to = $now->copy()->addMinutes((int) round($toHours * 60));

        $this->chunkFlights(function (Order $order) use ($callback, $from, $to): void {
            $departure = OrderNotificationContext::departureTime($order);
            $customer = $order->customer;

            if ($departure === null || $customer === null || $departure->lt($from) || $departure->gt($to)) {
                return;
            }

            $callback($order, $customer);
        });
    }

    /**
     * Arrival windows: negative fromHours means shortly before landing.
     *
     * @param  callable(Order, User): void  $callback
     */
    private function eachFlightByArrival(Carbon $now, float $fromHoursAfterArrival, float $toHoursAfterArrival, callable $callback): void
    {
        $from = $now->copy()->subMinutes((int) round($toHoursAfterArrival * 60));
        $to = $now->copy()->subMinutes((int) round($fromHoursAfterArrival * 60));

        $this->chunkFlights(function (Order $order) use ($callback, $from, $to): void {
            $arrival = OrderNotificationContext::arrivalTime($order);
            $customer = $order->customer;

            if ($arrival === null || $customer === null || $arrival->lt($from) || $arrival->gt($to)) {
                return;
            }

            $callback($order, $customer);
        });
    }

    /**
     * @param  callable(Order): void  $callback
     */
    private function chunkFlights(callable $callback): void
    {
        Order::query()
            ->with('customer')
            ->where('service_type', Order::SERVICE_TYPE_FLIGHT)
            ->whereIn('status', [
                Order::STATUS_CONFIRMED,
                Order::STATUS_TICKETED,
                Order::STATUS_COMPLETED,
            ])
            ->whereNotNull('details')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($callback): void {
                foreach ($orders as $order) {
                    $callback($order);
                }
            });
    }

    private function alreadySent(int $orderId, string $templateCode): bool
    {
        return UserNotification::query()
            ->where('related_type', 'order')
            ->where('related_id', $orderId)
            ->where('template_code', $templateCode)
            ->exists();
    }
}
