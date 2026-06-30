<?php

namespace App\Observers;

use App\Models\FinancialTransaction;
use App\Modules\Admin\Governance\Events\TransactionRecorded;
use App\Modules\Admin\Governance\Services\GovernanceEventDispatcher;
use App\Modules\Admin\Finance\Services\FinancialLedgerService;
use Illuminate\Support\Facades\Schema;

class FinancialTransactionObserver
{
    public function __construct(
        private readonly FinancialLedgerService $financialLedgerService,
        private readonly GovernanceEventDispatcher $governanceEventDispatcher,
    ) {
    }

    public function created(FinancialTransaction $financialTransaction): void
    {
        if (! Schema::hasTable('financial_ledger_entries')) {
            return;
        }

        $this->financialLedgerService->postTransaction($financialTransaction);

        $this->governanceEventDispatcher->dispatch(new TransactionRecorded(
            transactionId: $financialTransaction->id,
            orderId: $financialTransaction->order_id,
            type: $financialTransaction->type,
            status: $financialTransaction->status ?: FinancialTransaction::STATUS_EXECUTED,
            amount: number_format((float) $financialTransaction->amount, 2, '.', ''),
            currency: $financialTransaction->currency,
            source: $financialTransaction->source,
            sourceId: $financialTransaction->source_id,
            referenceType: $financialTransaction->reference_type,
            referenceId: $financialTransaction->reference_id,
            debitAccount: $financialTransaction->debit_account,
            creditAccount: $financialTransaction->credit_account,
            performedByType: $financialTransaction->performed_by_type,
            performedById: $financialTransaction->performed_by_id,
            metadata: $financialTransaction->metadata ?? [],
            occurredAt: $financialTransaction->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ));
    }
}