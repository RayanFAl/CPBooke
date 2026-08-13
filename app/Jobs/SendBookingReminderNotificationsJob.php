<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\UserNotification;
use App\Modules\Notifications\Services\NotificationPreferenceResolver;
use App\Modules\Notifications\Support\NotificationTopics;
use App\Modules\Notifications\Support\OrderNotificationContext;
use App\Modules\Orders\Events\BookingReminderDue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class SendBookingReminderNotificationsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(NotificationPreferenceResolver $preferenceResolver): void
    {
        $now = now();

        $this->dispatchFlightWindow(
            preferenceResolver: $preferenceResolver,
            window: BookingReminderDue::WINDOW_24H,
            templateCode: 'FLIGHT_REMINDER_24H',
            from: $now->copy()->addHours(23),
            to: $now->copy()->addHours(25),
        );

        $this->dispatchFlightWindow(
            preferenceResolver: $preferenceResolver,
            window: BookingReminderDue::WINDOW_3H,
            templateCode: 'FLIGHT_REMINDER_3H',
            from: $now->copy()->addHours(2)->addMinutes(30),
            to: $now->copy()->addHours(3)->addMinutes(30),
        );

        $this->dispatchHotelCheckInReminders($preferenceResolver, $now);
    }

    private function dispatchFlightWindow(
        NotificationPreferenceResolver $preferenceResolver,
        string $window,
        string $templateCode,
        Carbon $from,
        Carbon $to,
    ): void {
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
            ->chunkById(100, function ($orders) use ($preferenceResolver, $window, $templateCode, $from, $to): void {
                foreach ($orders as $order) {
                    $departure = OrderNotificationContext::departureTime($order);

                    if ($departure === null || $departure->lt($from) || $departure->gt($to)) {
                        continue;
                    }

                    $customer = $order->customer;

                    if ($customer === null) {
                        continue;
                    }

                    if (! $preferenceResolver->topicEnabled($customer, NotificationTopics::BOOKING_REMINDERS)) {
                        continue;
                    }

                    if ($this->alreadySent($order->id, $templateCode)) {
                        continue;
                    }

                    event(new BookingReminderDue($order, $window));
                }
            });
    }

    private function dispatchHotelCheckInReminders(
        NotificationPreferenceResolver $preferenceResolver,
        Carbon $now,
    ): void {
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
            ->chunkById(100, function ($orders) use ($preferenceResolver, $targetDay): void {
                foreach ($orders as $order) {
                    $checkIn = OrderNotificationContext::checkInDate($order);

                    if ($checkIn === null || $checkIn->toDateString() !== $targetDay) {
                        continue;
                    }

                    $customer = $order->customer;

                    if ($customer === null) {
                        continue;
                    }

                    if (
                        ! $preferenceResolver->topicEnabled($customer, NotificationTopics::BOOKING_REMINDERS)
                        || ! $preferenceResolver->topicEnabled($customer, NotificationTopics::HOTEL)
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

    private function alreadySent(int $orderId, string $templateCode): bool
    {
        return UserNotification::query()
            ->where('related_type', 'order')
            ->where('related_id', $orderId)
            ->where('template_code', $templateCode)
            ->exists();
    }
}
