<?php

namespace App\Console\Commands;

use App\Modules\Admin\Finance\Jobs\RunFinancialReconciliationJob;
use App\Modules\Admin\Finance\Services\FinancialConsistencyService;
use Illuminate\Console\Command;

class ReconcileFinancialTruth extends Command
{
    protected $signature = 'finance:reconcile {--queue : Dispatch the reconciliation as a queued job} {--no-repair : Detect anomalies without creating missing ledger entries}';

    protected $description = 'Run financial truth reconciliation and repair missing ledger postings when possible.';

    public function handle(FinancialConsistencyService $financialConsistencyService): int
    {
        $repairMissingLedger = ! $this->option('no-repair');

        if ((bool) $this->option('queue')) {
            RunFinancialReconciliationJob::dispatch($repairMissingLedger);

            $this->info('Financial reconciliation job dispatched successfully.');

            return self::SUCCESS;
        }

        $summary = $financialConsistencyService->reconcile($repairMissingLedger);

        $this->table(['Metric', 'Value'], [
            ['Transactions scanned', $summary['transactions_scanned']],
            ['Orders scanned', $summary['orders_scanned']],
            ['Missing ledger entries found', $summary['missing_ledger_entries_found']],
            ['Missing ledger entries repaired', $summary['missing_ledger_entries_repaired']],
            ['Remaining anomalies', $summary['remaining_anomalies']],
        ]);

        return self::SUCCESS;
    }
}