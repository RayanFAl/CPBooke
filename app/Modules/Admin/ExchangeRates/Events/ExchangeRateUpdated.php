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
        public readonly string $oldBuy,
        public readonly string $newBuy,
        public readonly string $oldSell,
        public readonly string $newSell,
        public readonly ?User $actor = null,
    ) {
    }

    /**
     * Backward-compatible summary for templates that still expect old_rate/new_rate.
     */
    public function oldRateSummary(): string
    {
        return "buy {$this->oldBuy} / sell {$this->oldSell}";
    }

    public function newRateSummary(): string
    {
        return "buy {$this->newBuy} / sell {$this->newSell}";
    }
}
