<?php

namespace App\Modules\Admin\Finance\Jobs;

use App\Modules\Admin\Finance\Services\FinancialConsistencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunFinancialReconciliationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $queue = 'finance';

    public function __construct(
        public readonly bool $repairMissingLedger = true,
    ) {
    }

    public function handle(FinancialConsistencyService $financialConsistencyService): void
    {
        $financialConsistencyService->reconcile($this->repairMissingLedger);
    }
}