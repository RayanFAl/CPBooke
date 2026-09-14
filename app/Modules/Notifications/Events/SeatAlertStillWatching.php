<?php

namespace App\Modules\Notifications\Events;

use App\Models\SeatAlert;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeatAlertStillWatching
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SeatAlert $alert,
    ) {}
}
