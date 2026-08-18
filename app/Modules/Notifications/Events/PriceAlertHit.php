<?php

namespace App\Modules\Notifications\Events;

use App\Models\PriceAlert;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PriceAlertHit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PriceAlert $alert,
        public readonly float $currentPrice,
    ) {}
}
