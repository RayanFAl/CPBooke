<?php

namespace App\Modules\Notifications\Events;

use App\Models\TravelSearchIntent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AbandonedFlightSearchDue
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly TravelSearchIntent $intent,
    ) {}
}
