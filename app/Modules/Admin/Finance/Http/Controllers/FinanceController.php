<?php

namespace App\Modules\Admin\Finance\Http\Controllers;

use App\Modules\Admin\Finance\Services\FinanceAnalyticsService;
use App\Modules\Admin\Finance\Jobs\RunFinancialReconciliationJob;
use App\Modules\Admin\Finance\Services\FinanceReportingService;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController
{
    /**
     * Display the finance management shell.
     */
    public function index(Request $request, FinanceAnalyticsService $analyticsService, FinanceReportingService $financeReportingService): Response
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'service_type' => ['nullable', 'in:'.implode(',', Order::serviceTypes())],
            'country' => ['nullable', 'string', 'size:2'],
            'provider_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (! Schema::hasTable('financial_transactions')) {
            $emptyDashboard = [
                'counts' => [
                    'transactions' => 0,
                ],
                'totals' => [
                    'payments' => '0.00',
                    'refunds' => '0.00',
                    'net' => '0.00',
                ],
                'analytics' => $analyticsService->empty(),
                'activity' => [
                    'ledger_preview' => [],
                    'latest_transactions' => [],
                ],
                'kpis' => [
                    'gross_revenue' => '0.00',
                    'recognized_revenue' => '0.00',
                    'refunds' => '0.00',
                    'payouts' => '0.00',
                    'net_cash' => '0.00',
                    'gross_profit' => '0.00',
                ],
                'reconciliation' => [
                    'counts' => [
                        'transactions_missing_ledger' => 0,
                        'transactions_unbalanced' => 0,
                        'order_payment_mismatches' => 0,
                        'total' => 0,
                    ],
                    'items' => [],
                ],
                'intelligence' => [
                    'profit_per_country' => [],
                    'refund_impact' => [
                        'refunds_total' => '0.00',
                        'impacted_orders_count' => 0,
                        'by_service' => [],
                    ],
                    'commission_breakdown_per_provider' => [],
                    'customer_lifetime_value' => [],
                ],
                'drilldown' => [
                    'orders' => [],
                ],
            ];

            return Inertia::render('admin/finance/pages/Index', [
                'dashboard' => $emptyDashboard,
                'filters' => $filters,
                'filter_options' => $this->filterOptions(),
                'exports' => [
                    'csv' => route('admin.finance.export.csv', $filters, absolute: false),
                ],
                'metrics' => [
                    'transactions_count' => 0,
                    'payments_total' => '0.00',
                    'refunds_total' => '0.00',
                    'net_total' => '0.00',
                ],
                'latest_financial_event' => null,
                'ledger_preview' => [],
                'transactions' => [],
            ]);
        }

        $dashboard = $financeReportingService->dashboard($filters);
        $latestFinancialEvent = collect($dashboard['activity']['latest_transactions'])->isEmpty()
            ? null
            : [
                'transaction_id' => $dashboard['activity']['latest_transactions'][0]['id'],
                'type' => $dashboard['activity']['latest_transactions'][0]['type'],
                'source' => $dashboard['activity']['latest_transactions'][0]['source'],
                'order_id' => $dashboard['activity']['latest_transactions'][0]['order_id'],
                'amount' => $dashboard['activity']['latest_transactions'][0]['amount'],
                'currency' => $dashboard['activity']['latest_transactions'][0]['currency'],
                'created_at' => $dashboard['activity']['latest_transactions'][0]['created_at'],
            ];

        return Inertia::render('admin/finance/pages/Index', [
            'dashboard' => $dashboard,
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'exports' => [
                'csv' => route('admin.finance.export.csv', $filters, absolute: false),
            ],
            'metrics' => [
                'transactions_count' => $dashboard['counts']['transactions'],
                'payments_total' => $dashboard['totals']['payments'],
                'refunds_total' => $dashboard['totals']['refunds'],
                'net_total' => $dashboard['totals']['net'],
            ],
            'latest_financial_event' => $latestFinancialEvent,
            'ledger_preview' => $dashboard['activity']['ledger_preview'],
            'transactions' => $dashboard['activity']['latest_transactions'],
        ]);
    }

    /**
     * Backward-compatible invokable entry point.
     */
    public function __invoke(Request $request, FinanceAnalyticsService $analyticsService, FinanceReportingService $financeReportingService): Response
    {
        return $this->index($request, $analyticsService, $financeReportingService);
    }

    public function exportCsv(Request $request, FinanceReportingService $financeReportingService): StreamedResponse
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'service_type' => ['nullable', 'in:'.implode(',', Order::serviceTypes())],
            'country' => ['nullable', 'string', 'size:2'],
            'provider_name' => ['nullable', 'string', 'max:255'],
        ]);

        return $financeReportingService->exportCsv($filters);
    }

    public function reconcile(): RedirectResponse
    {
        RunFinancialReconciliationJob::dispatch();

        return back()->with('success', 'Financial reconciliation job queued successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'service_types' => array_map(fn (string $service): array => [
                'name' => $service,
                'label' => str_replace('_', ' ', ucfirst($service)),
            ], Order::serviceTypes()),
        ];
    }
}