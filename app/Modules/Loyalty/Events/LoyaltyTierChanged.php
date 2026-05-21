<?php

namespace App\Modules\Loyalty\Events;

use App\Models\LoyaltyHistory;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoyaltyTierChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly LoyaltyHistory $history,
    ) {
    }
}