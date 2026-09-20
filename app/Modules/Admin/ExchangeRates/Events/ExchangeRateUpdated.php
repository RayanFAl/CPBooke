<?php

namespace App\Modules\Admin\ExchangeRates\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExchangeRateUpdated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<array{
     *     currency_code: string,
     *     old_buy: string,
     *     new_buy: string,
     *     old_sell: string,
     *     new_sell: string
     * }>  $changes
     */
    public function __construct(
        public readonly array $changes,
        public readonly ?User $actor = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function currencyCodes(): array
    {
        return array_values(array_map(
            static fn (array $change): string => (string) ($change['currency_code'] ?? ''),
            $this->changes,
        ));
    }

    public function currencyCodesLabel(): string
    {
        return implode(', ', array_values(array_filter($this->currencyCodes())));
    }
}
