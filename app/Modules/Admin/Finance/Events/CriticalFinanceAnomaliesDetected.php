<?php

namespace App\Modules\Admin\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CriticalFinanceAnomaliesDetected
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $anomalies
     */
    public function __construct(
        public readonly array $anomalies,
    ) {
    }
}