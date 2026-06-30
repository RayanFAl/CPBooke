<?php

namespace App\Modules\Admin\Finance\Services;

use App\Models\FinancialLedgerEntry;
use App\Models\FinancialTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialLedgerService
{
    /**
     * Post a financial transaction into the immutable double-entry ledger.
     *
     * @return Collection<int, FinancialLedgerEntry>
     */
    public function postTransaction(FinancialTransaction $financialTransaction): Collection
    {
        $existing = FinancialLedgerEntry::query()
            ->where('financial_transaction_id', $financialTransaction->id)
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        [$debitAccount, $creditAccount] = $this->resolveAccounts($financialTransaction);

        return DB::transaction(function () use ($financialTransaction, $debitAccount, $creditAccount): Collection {
            $postedAt = $financialTransaction->created_at ?: now();
            $referenceType = $financialTransaction->reference_type ?: FinancialTransaction::REFERENCE_TYPE_ORDER;
            $referenceId = $financialTransaction->reference_id ?: $financialTransaction->order_id;
            $baseAttributes = [
                'financial_transaction_id' => $financialTransaction->id,
                'order_id' => $financialTransaction->order_id,
                'amount' => number_format((float) $financialTransaction->amount, 2, '.', ''),
                'currency' => $financialTransaction->currency,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'posted_at' => $postedAt,
                'metadata' => [
                    'transaction_type' => $financialTransaction->type,
                    'transaction_source' => $financialTransaction->source,
                    'transaction_status' => $financialTransaction->status,
                ],
            ];

            FinancialLedgerEntry::query()->create([
                ...$baseAttributes,
                'entry_type' => FinancialLedgerEntry::ENTRY_TYPE_DEBIT,
                'account_code' => $debitAccount,
            ]);

            FinancialLedgerEntry::query()->create([
                ...$baseAttributes,
                'entry_type' => FinancialLedgerEntry::ENTRY_TYPE_CREDIT,
                'account_code' => $creditAccount,
            ]);

            return FinancialLedgerEntry::query()
                ->where('financial_transaction_id', $financialTransaction->id)
                ->orderBy('id')
                ->get();
        });
    }

    /**
     * Determine whether the posted journal rows are balanced.
     */
    public function isBalanced(FinancialTransaction $financialTransaction): bool
    {
        $entries = FinancialLedgerEntry::query()
            ->where('financial_transaction_id', $financialTransaction->id)
            ->get();

        if ($entries->count() !== 2) {
            return false;
        }

        $debits = (float) $entries
            ->where('entry_type', FinancialLedgerEntry::ENTRY_TYPE_DEBIT)
            ->sum('amount');
        $credits = (float) $entries
            ->where('entry_type', FinancialLedgerEntry::ENTRY_TYPE_CREDIT)
            ->sum('amount');

        return round($debits, 2) === round($credits, 2);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveAccounts(FinancialTransaction $financialTransaction): array
    {
        if (filled($financialTransaction->debit_account) && filled($financialTransaction->credit_account)) {
            return [
                (string) $financialTransaction->debit_account,
                (string) $financialTransaction->credit_account,
            ];
        }

        $mapping = FinancialTransaction::ledgerMappingForType($financialTransaction->type);

        return [$mapping['debit_account'], $mapping['credit_account']];
    }
}