<?php

namespace App\Modules\Admin\ExchangeRates\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExchangeRateUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $currencyCode,
        public readonly string $oldRate,
        public readonly string $newRate,
        public readonly ?User $actor = null,
    ) {
    }
}
