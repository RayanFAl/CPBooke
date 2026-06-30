<?php

namespace App\Modules\Notifications\Services;

class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationEngine $notificationEngine,
    ) {
    }

    public function dispatch(object $event): void
    {
        $this->notificationEngine->dispatch($event);
    }
}