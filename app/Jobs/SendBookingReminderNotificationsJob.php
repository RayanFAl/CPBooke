<?php

namespace App\Jobs;

use App\Modules\Notifications\Services\JourneyCampaignDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBookingReminderNotificationsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(JourneyCampaignDispatcher $dispatcher): void
    {
        $dispatcher->dispatch(now());
    }
}
