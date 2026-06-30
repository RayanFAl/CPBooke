<?php

namespace App\Modules\Admin\Finance\Services;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Modules\Admin\Finance\Events\CriticalFinanceAnomaliesDetected;
use App\Modules\Admin\Governance\Events\FinanceAnomalyDetected;
use App\Modules\Admin\Governance\Events\TransactionReconciled;
use App\Modules\Admin\Governance\Services\GovernanceEventDispatcher;
use App\Support\Rbac\RbacAuditLogger;
use App\Support\Rbac\RbacAuthorizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class FinancialConsistencyService
{
    public function __construct(
        private readonly FinancialLedgerService $financialLedgerService,
        private readonly RbacAuthorizer $rbacAuthorizer,
        private readonly RbacAuditLogger $rbacAuditLogger,
        private readonly GovernanceEventDispatcher $governanceEventDispatcher,
    ) {
    }

    /**
     * Build an anomaly summary and sample rows for the finance dashboard.
     *
     * @return array<string, mixed>
     */
    public function summarize(array $filters = [], int $limit = 12): array
    {
        $this->rbacAuthorizer->authorize('finance.view', allowSystem: true);

        $transactionAnomalies = $this->transactionAnomalies($filters);
        $orderAnomalies = $this->orderAnomalies($filters);
        $anomalies = $transactionAnomalies
            ->concat($orderAnomalies)
            ->sortByDesc('detected_at')
            ->values();

        return [
            'counts' => [
                'transactions_missing_ledger' => $transactionAnomalies->where('code', 'missing_ledger_entries')->count(),
                'transactions_unbalanced' => $transactionAnomalies->where('code', 'unbalanced_ledger_entries')->count(),
                'order_payment_mismatches' => $orderAnomalies->count(),
                'total' => $anomalies->count(),
            ],
            'items' => $anomalies->take($limit)->all(),
        ];
    }

    /**
     * Repair missing ledger rows and return a reconciliation summary.
     *
     * @return array<string, int>
     */
    public function reconcile(bool $repairMissingLedger = true): array
    {
        $actor = $this->rbacAuthorizer->authorize('finance.reconcile', allowSystem: true);

        if (! $this->ledgerEntriesTableExists()) {
            $summary = [
                'transactions_scanned' => FinancialTransaction::query()->count(),
                'orders_scanned' => Order::query()->count(),
                'missing_ledger_entries_found' => 0,
                'missing_ledger_entries_repaired' => 0,
                'remaining_anomalies' => $this->summarize()['counts']['total'],
            ];

            $this->rbacAuditLogger->log('finance.reconcile.executed', 'finance.reconcile', $actor, 'finance_reconciliation', null, [
                'repair_missing_ledger' => $repairMissingLedger,
                'summary' => $summary,
                'critical_anomalies_count' => 0,
                'ledger_entries_table_missing' => true,
            ]);

            return $summary;
        }

        $missingLedgerTransactions = FinancialTransaction::query()
            ->doesntHave('ledgerEntries')
            ->get();

        $repaired = 0;

        if ($repairMissingLedger) {
            foreach ($missingLedgerTransactions as $transaction) {
                $this->financialLedgerService->postTransaction($transaction);
                $repaired++;
            }
        }

        $summary = [
            'transactions_scanned' => FinancialTransaction::query()->count(),
            'orders_scanned' => Order::query()->count(),
            'missing_ledger_entries_found' => $missingLedgerTransactions->count(),
            'missing_ledger_entries_repaired' => $repaired,
            'remaining_anomalies' => $this->summarize()['counts']['total'],
        ];

        $criticalAnomalies = collect($this->summarize()['items'])
            ->where('severity', 'critical')
            ->values()
            ->all();

        if ($criticalAnomalies !== []) {
            event(new CriticalFinanceAnomaliesDetected($criticalAnomalies));
        }

        $this->rbacAuditLogger->log('finance.reconcile.executed', 'finance.reconcile', $actor, 'finance_reconciliation', null, [
            'repair_missing_ledger' => $repairMissingLedger,
            'summary' => $summary,
            'critical_anomalies_count' => count($criticalAnomalies),
        ]);

        $this->governanceEventDispatcher->dispatch(new TransactionReconciled(
            actorId: $actor?->id,
            actorType: $actor ? 'user' : 'system',
            repairMissingLedger: $repairMissingLedger,
            summary: $summary,
            criticalAnomaliesCount: count($criticalAnomalies),
            occurredAt: now()->toIso8601String(),
        ));

        foreach ($criticalAnomalies as $anomaly) {
            $this->governanceEventDispatcher->dispatch(new FinanceAnomalyDetected(
                code: (string) ($anomaly['code'] ?? 'unknown_anomaly'),
                severity: (string) ($anomaly['severity'] ?? 'unknown'),
                message: (string) ($anomaly['message'] ?? 'Unknown finance anomaly detected.'),
                order: isset($anomaly['order']) && is_array($anomaly['order']) ? $anomaly['order'] : null,
                transaction: isset($anomaly['transaction']) && is_array($anomaly['transaction']) ? $anomaly['transaction'] : null,
                context: [
                    'source' => 'finance.reconcile',
                    'repair_missing_ledger' => $repairMissingLedger,
                ],
                detectedAt: (string) ($anomaly['detected_at'] ?? now()->toIso8601String()),
                occurredAt: now()->toIso8601String(),
            ));
        }

        return $summary;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function transactionAnomalies(array $filters): Collection
    {
        if (! $this->ledgerEntriesTableExists()) {
            return collect();
        }

        return FinancialTransaction::query()
            ->with(['ledgerEntries', 'order.customer'])
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['service_type'] ?? null, fn ($query, $serviceType) => $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('service_type', $serviceType)))
            ->when($filters['country'] ?? null, fn ($query, $country) => $query->whereHas('order.customer', fn ($userQuery) => $userQuery->where('country', $country)))
            ->when($filters['provider_name'] ?? null, fn ($query, $provider) => $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('provider_name', 'like', "%{$provider}%")))
            ->get()
            ->flatMap(function (FinancialTransaction $transaction): array {
                $items = [];

                if ($transaction->ledgerEntries->isEmpty()) {
                    $items[] = $this->anomalyPayload(
                        code: 'missing_ledger_entries',
                        severity: 'high',
                        message: 'Financial transaction exists without posted ledger entries.',
                        detectedAt: $transaction->created_at?->toIso8601String(),
                        transaction: $transaction,
                    );
                } elseif (! $this->financialLedgerService->isBalanced($transaction)) {
                    $items[] = $this->anomalyPayload(
                        code: 'unbalanced_ledger_entries',
                        severity: 'critical',
                        message: 'Ledger entries are not balanced for this financial transaction.',
                        detectedAt: $transaction->created_at?->toIso8601String(),
                        transaction: $transaction,
                    );
                }

                return $items;
            });
    }

    private function ledgerEntriesTableExists(): bool
    {
        return Schema::hasTable('financial_ledger_entries');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function orderAnomalies(array $filters): Collection
    {
        return Order::query()
            ->with(['transactions', 'customer'])
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['service_type'] ?? null, fn ($query, $serviceType) => $query->where('service_type', $serviceType))
            ->when($filters['country'] ?? null, fn ($query, $country) => $query->whereHas('customer', fn ($userQuery) => $userQuery->where('country', $country)))
            ->when($filters['provider_name'] ?? null, fn ($query, $provider) => $query->where('provider_name', 'like', "%{$provider}%"))
            ->get()
            ->flatMap(function (Order $order): array {
                $items = [];
                $derivedPaymentStatus = $order->derivePaymentStatus();

                if ($order->payment_status !== $derivedPaymentStatus) {
                    $items[] = [
                        'code' => 'payment_status_mismatch',
                        'severity' => 'high',
                        'message' => 'Persisted payment status does not match the financial transaction trail.',
                        'detected_at' => $order->updated_at?->toIso8601String(),
                        'order' => [
                            'id' => $order->id,
                            'booking_reference' => $order->booking_reference,
                            'service_type' => $order->service_type,
                            'provider_name' => $order->provider_name,
                        ],
                        'transaction' => null,
                    ];
                }

                if ((float) $order->getNetPaidAmount() > (float) $order->total_amount) {
                    $items[] = [
                        'code' => 'over_collected_order',
                        'severity' => 'critical',
                        'message' => 'Net paid amount exceeds the order total.',
                        'detected_at' => $order->updated_at?->toIso8601String(),
                        'order' => [
                            'id' => $order->id,
                            'booking_reference' => $order->booking_reference,
                            'service_type' => $order->service_type,
                            'provider_name' => $order->provider_name,
                        ],
                        'transaction' => null,
                    ];
                }

                return $items;
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function anomalyPayload(string $code, string $severity, string $message, ?string $detectedAt, FinancialTransaction $transaction): array
    {
        return [
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
            'detected_at' => $detectedAt,
            'order' => [
                'id' => $transaction->order?->id,
                'booking_reference' => $transaction->order?->booking_reference,
                'service_type' => $transaction->order?->service_type,
                'provider_name' => $transaction->order?->provider_name,
            ],
            'transaction' => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'source' => $transaction->source,
                'amount' => number_format((float) $transaction->amount, 2, '.', ''),
                'currency' => $transaction->currency,
            ],
        ];
    }
}