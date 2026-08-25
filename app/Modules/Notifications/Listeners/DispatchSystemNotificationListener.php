<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchSystemNotificationListener implements ShouldQueue
{
    public string $queue = 'notifications-dispatch';

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(object $event): void
    {
        $this->notificationService->dispatchForEvent($event);
    }
}
