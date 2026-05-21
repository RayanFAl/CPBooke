<?php

namespace App\Modules\Admin\Finance\Services;

use App\Models\FinancialLedgerEntry;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Support\Rbac\RbacAuditLogger;
use App\Support\Rbac\RbacAuthorizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportingService
{
    public function __construct(
        private readonly FinanceAnalyticsService $financeAnalyticsService,
        private readonly FinancialConsistencyService $financialConsistencyService,
        private readonly RbacAuthorizer $rbacAuthorizer,
        private readonly RbacAuditLogger $rbacAuditLogger,
    ) {
    }

    /**
     * Build the finance dashboard payload.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters = []): array
    {
        $actor = $this->rbacAuthorizer->authorize('finance.view', allowSystem: true);
        $this->rbacAuditLogger->log('finance.dashboard.viewed', 'finance.view', $actor, 'finance_dashboard', null, [
            'filters' => $filters,
        ]);

        $rows = $this->transactionFacts($filters);
        $paymentsTotal = $this->sumByType($rows, FinancialTransaction::TYPE_PAYMENT);
        $refundsTotal = $this->sumByType($rows, FinancialTransaction::TYPE_REFUND);
        $commissionsTotal = $this->sumByType($rows, FinancialTransaction::TYPE_COMMISSION);
        $payoutsTotal = $this->sumByType($rows, FinancialTransaction::TYPE_PAYOUT);
        $compensationsTotal = $this->sumByType($rows, FinancialTransaction::TYPE_COMPENSATION);
        $adjustmentsTotal = $this->sumByType($rows, FinancialTransaction::TYPE_ADJUSTMENT);
        $latestTransactions = $rows->sortByDesc('created_at')->values()->take(20)->values();
        $analytics = $this->financeAnalyticsService->build($filters);
        $reconciliation = $this->financialConsistencyService->summarize($filters);

        return [
            'counts' => [
                'transactions' => $rows->count(),
            ],
            'totals' => [
                'payments' => number_format($paymentsTotal, 2, '.', ''),
                'refunds' => number_format($refundsTotal, 2, '.', ''),
                'net' => number_format($paymentsTotal - $refundsTotal, 2, '.', ''),
            ],
            'kpis' => [
                'gross_revenue' => number_format($paymentsTotal, 2, '.', ''),
                'recognized_revenue' => number_format($commissionsTotal, 2, '.', ''),
                'refunds' => number_format($refundsTotal, 2, '.', ''),
                'payouts' => number_format($payoutsTotal, 2, '.', ''),
                'net_cash' => number_format($paymentsTotal - $refundsTotal - $payoutsTotal, 2, '.', ''),
                'gross_profit' => number_format($commissionsTotal - $refundsTotal - $payoutsTotal - $compensationsTotal - $adjustmentsTotal, 2, '.', ''),
            ],
            'analytics' => $analytics,
            'reconciliation' => $reconciliation,
            'intelligence' => [
                'profit_per_country' => $this->profitPerCountry($rows),
                'refund_impact' => $this->refundImpact($rows),
                'commission_breakdown_per_provider' => $this->commissionBreakdownPerProvider($rows),
                'customer_lifetime_value' => $this->customerLifetimeValue($rows),
            ],
            'activity' => [
                'ledger_preview' => $this->ledgerPreview($filters),
                'latest_transactions' => $latestTransactions
                    ->map(fn (object $row): array => $this->transactionPayload($row))
                    ->all(),
            ],
            'drilldown' => [
                'orders' => $this->orderDrilldown($rows),
            ],
        ];
    }

    /**
     * Stream the drill-down rows as CSV.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(array $filters = []): StreamedResponse
    {
        $actor = $this->rbacAuthorizer->authorize('finance.export', allowSystem: true);
        $this->rbacAuditLogger->log('finance.report.exported', 'finance.export', $actor, 'finance_report', null, [
            'filters' => $filters,
            'format' => 'csv',
        ]);

        $rows = $this->orderDrilldown($this->transactionFacts($filters));

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Order ID',
                'Booking Reference',
                'Provider',
                'Service Type',
                'Country',
                'Payments',
                'Refunds',
                'Commissions',
                'Payouts',
                'Net Cash',
                'Gross Profit',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['order_id'],
                    $row['booking_reference'],
                    $row['provider_name'],
                    $row['service_type'],
                    $row['country'],
                    $row['payments_total'],
                    $row['refunds_total'],
                    $row['commissions_total'],
                    $row['payouts_total'],
                    $row['net_cash'],
                    $row['gross_profit'],
                ]);
            }

            fclose($handle);
        }, 'finance-report.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function transactionFacts(array $filters): Collection
    {
        return $this->baseFactsQuery($filters)
            ->orderByDesc('ft.created_at')
            ->orderByDesc('ft.id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Query\Builder
     */
    private function baseFactsQuery(array $filters)
    {
        $statusSelect = Schema::hasColumn('financial_transactions', 'status')
            ? 'ft.status'
            : sprintf("'%s' as status", FinancialTransaction::STATUS_EXECUTED);

        return DB::table('financial_transactions as ft')
            ->leftJoin('orders as o', 'ft.order_id', '=', 'o.id')
            ->leftJoin('users as customers', 'o.customer_id', '=', 'customers.id')
            ->select([
                'ft.id',
                'ft.order_id',
                'ft.type',
                'ft.amount',
                'ft.currency',
                'ft.source',
                'ft.created_at',
                'o.booking_reference',
                'o.provider_name',
                'o.service_type',
                'o.total_amount as order_total_amount',
                'customers.id as customer_id',
                'customers.full_name as customer_full_name',
                'customers.name as customer_name',
                'customers.country as customer_country',
            ])
            ->selectRaw($statusSelect)
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('ft.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('ft.created_at', '<=', $date))
            ->when($filters['service_type'] ?? null, fn ($query, $serviceType) => $query->where('o.service_type', $serviceType))
            ->when($filters['country'] ?? null, fn ($query, $country) => $query->where('customers.country', $country))
            ->when($filters['provider_name'] ?? null, fn ($query, $provider) => $query->where('o.provider_name', 'like', "%{$provider}%"));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ledgerPreview(array $filters): array
    {
        if (! Schema::hasTable('financial_ledger_entries')) {
            return [];
        }

        return FinancialLedgerEntry::query()
            ->with('financialTransaction')
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('posted_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('posted_at', '<=', $date))
            ->latest('posted_at')
            ->latest('id')
            ->get()
            ->groupBy('financial_transaction_id')
            ->take(20)
            ->map(function (Collection $entries): array {
                $debit = $entries->firstWhere('entry_type', FinancialLedgerEntry::ENTRY_TYPE_DEBIT);
                $credit = $entries->firstWhere('entry_type', FinancialLedgerEntry::ENTRY_TYPE_CREDIT);
                $transaction = $entries->first()?->financialTransaction;

                return [
                    'transaction_id' => $transaction?->id,
                    'type' => $transaction?->type,
                    'status' => $transaction?->status,
                    'debit_account' => $debit?->account_code,
                    'credit_account' => $credit?->account_code,
                    'amount' => number_format((float) ($debit?->amount ?? 0), 2, '.', ''),
                    'currency' => $debit?->currency,
                    'reference_type' => $debit?->reference_type,
                    'reference_id' => $debit?->reference_id,
                    'balanced' => round((float) ($debit?->amount ?? 0), 2) === round((float) ($credit?->amount ?? 0), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function orderDrilldown(Collection $rows): array
    {
        return $rows
            ->filter(fn (object $row): bool => $row->order_id !== null)
            ->groupBy('order_id')
            ->map(function (Collection $group): array {
                $first = $group->first();
                $payments = $this->sumByType($group, FinancialTransaction::TYPE_PAYMENT);
                $refunds = $this->sumByType($group, FinancialTransaction::TYPE_REFUND);
                $commissions = $this->sumByType($group, FinancialTransaction::TYPE_COMMISSION);
                $payouts = $this->sumByType($group, FinancialTransaction::TYPE_PAYOUT);
                $compensations = $this->sumByType($group, FinancialTransaction::TYPE_COMPENSATION);
                $adjustments = $this->sumByType($group, FinancialTransaction::TYPE_ADJUSTMENT);

                return [
                    'order_id' => $first->order_id,
                    'booking_reference' => $first->booking_reference,
                    'provider_name' => $first->provider_name,
                    'service_type' => $first->service_type ?: $this->resolveServiceKey((string) $first->source),
                    'country' => $this->resolveCountry($first),
                    'customer_name' => $first->customer_full_name ?: $first->customer_name,
                    'payments_total' => number_format($payments, 2, '.', ''),
                    'refunds_total' => number_format($refunds, 2, '.', ''),
                    'commissions_total' => number_format($commissions, 2, '.', ''),
                    'payouts_total' => number_format($payouts, 2, '.', ''),
                    'net_cash' => number_format($payments - $refunds - $payouts, 2, '.', ''),
                    'gross_profit' => number_format($commissions - $refunds - $payouts - $compensations - $adjustments, 2, '.', ''),
                ];
            })
            ->sortByDesc('net_cash')
            ->values()
            ->take(20)
            ->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array<string, string|int>>
     */
    private function profitPerCountry(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (object $row): string => $this->resolveCountry($row))
            ->map(function (Collection $group, string $country): array {
                $profit = $group->sum(fn (object $row): float => $this->profitContribution($row));

                return [
                    'key' => $country,
                    'count' => $group->pluck('order_id')->filter()->unique()->count(),
                    'total' => number_format($profit, 2, '.', ''),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, mixed>
     */
    private function refundImpact(Collection $rows): array
    {
        $refundRows = $rows->where('type', FinancialTransaction::TYPE_REFUND);

        return [
            'refunds_total' => number_format((float) $refundRows->sum('amount'), 2, '.', ''),
            'impacted_orders_count' => $refundRows->pluck('order_id')->filter()->unique()->count(),
            'by_service' => $refundRows
                ->groupBy(fn (object $row): string => $row->service_type ?: $this->resolveServiceKey((string) $row->source))
                ->map(fn (Collection $group, string $service): array => [
                    'key' => $service,
                    'count' => $group->count(),
                    'total' => number_format((float) $group->sum('amount'), 2, '.', ''),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array<string, string|int>>
     */
    private function commissionBreakdownPerProvider(Collection $rows): array
    {
        return $rows
            ->where('type', FinancialTransaction::TYPE_COMMISSION)
            ->groupBy(fn (object $row): string => $row->provider_name ?: 'unknown')
            ->map(fn (Collection $group, string $provider): array => [
                'key' => $provider,
                'count' => $group->count(),
                'total' => number_format((float) $group->sum('amount'), 2, '.', ''),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function customerLifetimeValue(Collection $rows): array
    {
        return $rows
            ->filter(fn (object $row): bool => $row->customer_id !== null)
            ->groupBy('customer_id')
            ->map(function (Collection $group): array {
                $first = $group->first();
                $payments = $this->sumByType($group, FinancialTransaction::TYPE_PAYMENT);
                $refunds = $this->sumByType($group, FinancialTransaction::TYPE_REFUND);

                return [
                    'customer_id' => $first->customer_id,
                    'customer_name' => $first->customer_full_name ?: $first->customer_name,
                    'country' => $this->resolveCountry($first),
                    'orders_count' => $group->pluck('order_id')->filter()->unique()->count(),
                    'net_value' => number_format($payments - $refunds, 2, '.', ''),
                ];
            })
            ->sortByDesc('net_value')
            ->values()
            ->take(10)
            ->all();
    }

    private function sumByType(Collection $rows, string $type): float
    {
        return (float) $rows
            ->where('type', $type)
            ->sum('amount');
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionPayload(object $row): array
    {
        return [
            'id' => $row->id,
            'type' => $row->type,
            'amount' => number_format((float) $row->amount, 2, '.', ''),
            'currency' => $row->currency,
            'source' => $row->source,
            'order_id' => $row->order_id,
            'booking_reference' => $row->booking_reference,
            'provider_name' => $row->provider_name,
            'service_type' => $row->service_type ?: $this->resolveServiceKey((string) $row->source),
            'country' => $this->resolveCountry($row),
            'created_at' => $row->created_at,
        ];
    }

    private function resolveCountry(object $row): string
    {
        $resolvedCountry = $this->resolveCountryKey((string) $row->source);

        return $resolvedCountry !== 'UNKNOWN' ? $resolvedCountry : ($row->customer_country ?: 'UNKNOWN');
    }

    private function resolveServiceKey(string $source): string
    {
        $normalized = strtolower($source);

        foreach (Order::serviceTypes() as $service) {
            if (str_contains($normalized, $service)) {
                return $service;
            }
        }

        return 'unknown';
    }

    private function resolveCountryKey(string $source): string
    {
        $tokens = preg_split('/[^a-z0-9]+/', strtolower($source)) ?: [];

        foreach ($tokens as $token) {
            if (preg_match('/^[a-z]{2}$/', $token) === 1) {
                return strtoupper($token);
            }
        }

        return 'UNKNOWN';
    }

    private function profitContribution(object $row): float
    {
        $amount = (float) $row->amount;

        return match ($row->type) {
            FinancialTransaction::TYPE_COMMISSION => $amount,
            FinancialTransaction::TYPE_PAYOUT,
            FinancialTransaction::TYPE_REFUND,
            FinancialTransaction::TYPE_COMPENSATION,
            FinancialTransaction::TYPE_ADJUSTMENT => -$amount,
            default => 0.0,
        };
    }
}