<?php

namespace App\Modules\Support\Services;

use Illuminate\Broadcasting\BroadcastException;

class SupportBroadcastService
{
    public function dispatch(object $event): void
    {
        try {
            event($event);
        } catch (BroadcastException $exception) {
            report($exception);
        }
    }
}
