<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Events\SeatAlertAvailable;
use App\Modules\Notifications\Events\SeatAlertStillWatching;
use App\Modules\Notifications\Services\NotificationService;

/**
 * Seat-alert pushes must not depend on a queue worker.
 */
class DispatchSeatAlertNotificationListener
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(SeatAlertAvailable|SeatAlertStillWatching $event): void
    {
        $this->notificationService->dispatchForEvent($event);
    }
}
