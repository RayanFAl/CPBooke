<?php

namespace App\Modules\Admin\Finance\Services;

use App\Models\FinancialTransaction;
use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class FinanceAnalyticsService
{
    private const ANALYTICS_CONTRACT_KEYS = [
        'time_series',
        'segmentation',
        'insights',
        'bi',
    ];

    /**
     * Build the finance analytics payload from transaction history.
     *
     * @return array{time_series: array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}, segmentation: array{by_type: array<int, array<string, int|string>>, by_source: array<int, array<string, int|string>>, by_currency: array<int, array<string, int|string>>}, insights: array{latest_event: array<string, int|string|null>|null, revenue_last_7_days: string, refunds_last_7_days: string}, bi: array{revenue_per_service: array<int, array<string, int|string>>, revenue_per_country: array<int, array<string, int|string>>, refund_rate: array{percentage: string, refunds_total: string, payments_total: string}, average_order_value: array{amount: string, orders_count: int}, transaction_mix: array<int, array<string, int|string>>, monthly_growth: array{current_period: string, previous_period: string, change_amount: string, change_percentage: string}}}
     */
    public function build(array $filters = []): array
    {
        $now = now();
        $cacheKey = $this->buildCacheKey($now, $filters);

        try {
            return $this->lockContract(Cache::remember(
                $cacheKey,
                $now->copy()->addMinutes(10),
                fn (): array => $this->computeAnalytics($now, $filters),
            ));
        } catch (Throwable) {
            return $this->lockContract($this->computeAnalytics($now, $filters));
        }
    }

    /**
     * Compute the finance analytics payload directly from transaction history.
     *
     * @return array{time_series: array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}, segmentation: array{by_type: array<int, array<string, int|string>>, by_source: array<int, array<string, int|string>>, by_currency: array<int, array<string, int|string>>}, insights: array{latest_event: array<string, int|string|null>|null, revenue_last_7_days: string, refunds_last_7_days: string}, bi: array{revenue_per_service: array<int, array<string, int|string>>, revenue_per_country: array<int, array<string, int|string>>, refund_rate: array{percentage: string, refunds_total: string, payments_total: string}, average_order_value: array{amount: string, orders_count: int}, transaction_mix: array<int, array<string, int|string>>, monthly_growth: array{current_period: string, previous_period: string, change_amount: string, change_percentage: string}}}
     */
    private function computeAnalytics($now, array $filters = []): array
    {
        $startDate = $now->copy()->startOfDay()->subDays(6);
        $allTransactions = $this->factsQuery($filters)
            ->get();
        $transactions = $allTransactions
            ->filter(fn (object $transaction): bool => ($transaction->created_at !== null ? now()->parse($transaction->created_at) : null)?->greaterThanOrEqualTo($startDate) ?? false)
            ->values();

        $latestTransaction = $allTransactions
            ->sortByDesc(fn (object $transaction): string => sprintf(
                '%s-%010d',
                $transaction->created_at ? now()->parse($transaction->created_at)->format('YmdHis') : '00000000000000',
                $transaction->id,
            ))
            ->first();

        $dailyBreakdown = collect(range(0, 6))
            ->map(function (int $offset) use ($startDate, $transactions): array {
                $date = $startDate->copy()->addDays($offset);
                $dayTransactions = $transactions->filter(
                    fn (object $transaction): bool => ($transaction->created_at !== null ? now()->parse($transaction->created_at) : null)?->isSameDay($date) ?? false,
                );

                $payments = (float) $dayTransactions
                    ->where('type', FinancialTransaction::TYPE_PAYMENT)
                    ->sum('amount');
                $refunds = (float) $dayTransactions
                    ->where('type', FinancialTransaction::TYPE_REFUND)
                    ->sum('amount');

                return [
                    'date' => $date->toDateString(),
                    'payments' => number_format($payments, 2, '.', ''),
                    'refunds' => number_format($refunds, 2, '.', ''),
                    'net' => number_format($payments - $refunds, 2, '.', ''),
                ];
            })
            ->values()
            ->all();

        return [
            'time_series' => [
                'labels' => array_map(
                    fn (array $day): string => $day['date'],
                    $dailyBreakdown,
                ),
                'datasets' => [
                    [
                        'label' => 'Revenue',
                        'key' => 'payments',
                        'data' => array_map(
                            fn (array $day): string => $day['payments'],
                            $dailyBreakdown,
                        ),
                    ],
                    [
                        'label' => 'Refunds',
                        'key' => 'refunds',
                        'data' => array_map(
                            fn (array $day): string => $day['refunds'],
                            $dailyBreakdown,
                        ),
                    ],
                    [
                        'label' => 'Net',
                        'key' => 'net',
                        'data' => array_map(
                            fn (array $day): string => $day['net'],
                            $dailyBreakdown,
                        ),
                    ],
                ],
            ],
            'segmentation' => [
                'by_type' => $this->segmentTransactions($transactions, 'type'),
                'by_source' => $this->segmentTransactions($transactions, 'source'),
                'by_currency' => $this->segmentTransactions($transactions, 'currency'),
            ],
            'insights' => [
                'latest_event' => $latestTransaction === null
                    ? null
                    : [
                        'transaction_id' => $latestTransaction->id,
                        'type' => $latestTransaction->type,
                        'source' => $latestTransaction->source,
                        'order_id' => $latestTransaction->order_id,
                        'amount' => number_format((float) $latestTransaction->amount, 2, '.', ''),
                        'currency' => $latestTransaction->currency,
                        'created_at' => $latestTransaction->created_at ? now()->parse($latestTransaction->created_at)->toDateTimeString() : null,
                    ],
                'revenue_last_7_days' => number_format(
                    (float) $transactions->where('type', FinancialTransaction::TYPE_PAYMENT)->sum('amount'),
                    2,
                    '.',
                    '',
                ),
                'refunds_last_7_days' => number_format(
                    (float) $transactions->where('type', FinancialTransaction::TYPE_REFUND)->sum('amount'),
                    2,
                    '.',
                    '',
                ),
            ],
            'bi' => $this->buildBi($allTransactions),
        ];
    }

    /**
     * Build the cache key for the analytics payload.
     */
    private function buildCacheKey($now, array $filters = []): string
    {
        $rangeStart = $now->copy()->startOfDay()->subDays(6)->toDateString();
        $rangeEnd = $now->copy()->toDateString();
        $transactionsCount = FinancialTransaction::query()->count();
        $latestTransactionId = FinancialTransaction::query()->max('id');
        $latestUpdate = FinancialTransaction::query()->max('updated_at');
        $filterHash = md5(json_encode(Arr::only($filters, ['date_from', 'date_to', 'service_type', 'country', 'provider_name'])) ?: '{}');

        return sprintf(
            'finance.analytics.%s.%s.%s.%s.%s.%s',
            $rangeStart,
            $rangeEnd,
            $transactionsCount,
            $latestTransactionId ?? 'none',
            $latestUpdate ? strtotime((string) $latestUpdate) : 'none',
            $filterHash,
        );
    }

    /**
     * Build the empty analytics payload for fallback states.
     *
     * @return array{time_series: array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}, segmentation: array{by_type: array<int, array<string, int|string>>, by_source: array<int, array<string, int|string>>, by_currency: array<int, array<string, int|string>>}, insights: array{latest_event: null, revenue_last_7_days: string, refunds_last_7_days: string}, bi: array{revenue_per_service: array<int, array<string, int|string>>, revenue_per_country: array<int, array<string, int|string>>, refund_rate: array{percentage: string, refunds_total: string, payments_total: string}, average_order_value: array{amount: string, orders_count: int}, transaction_mix: array<int, array<string, int|string>>, monthly_growth: array{current_period: string, previous_period: string, change_amount: string, change_percentage: string}}}
     */
    public function empty(): array
    {
        return $this->lockContract([
            'time_series' => $this->emptyChartData(),
            'segmentation' => [
                'by_type' => [],
                'by_source' => [],
                'by_currency' => [],
            ],
            'insights' => [
                'latest_event' => null,
                'revenue_last_7_days' => '0.00',
                'refunds_last_7_days' => '0.00',
            ],
            'bi' => [
                'revenue_per_service' => [],
                'revenue_per_country' => [],
                'refund_rate' => [
                    'percentage' => '0.00',
                    'refunds_total' => '0.00',
                    'payments_total' => '0.00',
                ],
                'average_order_value' => [
                    'amount' => '0.00',
                    'orders_count' => 0,
                ],
                'transaction_mix' => [],
                'monthly_growth' => [
                    'current_period' => '0.00',
                    'previous_period' => '0.00',
                    'change_amount' => '0.00',
                    'change_percentage' => '0.00',
                ],
            ],
        ]);
    }

    /**
     * Normalize analytics output to the official contract keys only.
     */
    private function lockContract(array $analytics): array
    {
        return [
            'time_series' => $analytics['time_series'],
            'segmentation' => $analytics['segmentation'],
            'insights' => $analytics['insights'],
            'bi' => $analytics['bi'],
        ];
    }

    /**
     * Group transactions by a field and summarize count and amount.
     *
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @return array<int, array<string, int|string>>
     */
    private function segmentTransactions(Collection $transactions, string $field): array
    {
        return $transactions
            ->groupBy(fn (object $transaction): string => (string) (($transaction->{$field} ?? null) ?: 'unknown'))
            ->map(function ($group, string $value): array {
                return [
                    'key' => $value,
                    'count' => $group->count(),
                    'total' => number_format((float) $group->sum('amount'), 2, '.', ''),
                ];
            })
            ->sortBy('key')
            ->values()
            ->all();
    }

    /**
     * Build business intelligence rollups from the existing transactions only.
     *
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @return array{revenue_per_service: array<int, array<string, int|string>>, revenue_per_country: array<int, array<string, int|string>>, refund_rate: array{percentage: string, refunds_total: string, payments_total: string}, average_order_value: array{amount: string, orders_count: int}, transaction_mix: array<int, array<string, int|string>>, monthly_growth: array{current_period: string, previous_period: string, change_amount: string, change_percentage: string}}
     */
    private function buildBi(Collection $transactions): array
    {
        $paymentTransactions = $transactions
            ->where('type', FinancialTransaction::TYPE_PAYMENT)
            ->values();
        $refundTransactions = $transactions
            ->where('type', FinancialTransaction::TYPE_REFUND)
            ->values();
        $mixTransactions = $transactions
            ->filter(fn (object $transaction): bool => in_array($transaction->type, [
                FinancialTransaction::TYPE_PAYMENT,
                FinancialTransaction::TYPE_REFUND,
                FinancialTransaction::TYPE_COMMISSION,
            ], true))
            ->values();
        $paymentsTotal = (float) $paymentTransactions->sum('amount');
        $refundsTotal = (float) $refundTransactions->sum('amount');
        $ordersCount = $paymentTransactions
            ->pluck('order_id')
            ->filter(fn ($orderId): bool => $orderId !== null)
            ->unique()
            ->count();
        $currentPeriodStart = now()->startOfDay()->subDays(29);
        $previousPeriodStart = $currentPeriodStart->copy()->subDays(30);
        $previousPeriodEnd = $currentPeriodStart->copy()->subDay()->endOfDay();
        $currentPeriodPayments = (float) $paymentTransactions
            ->filter(fn (object $transaction): bool => ($transaction->created_at !== null ? now()->parse($transaction->created_at) : null)?->between($currentPeriodStart, now()) ?? false)
            ->sum('amount');
        $previousPeriodPayments = (float) $paymentTransactions
            ->filter(fn (object $transaction): bool => ($transaction->created_at !== null ? now()->parse($transaction->created_at) : null)?->between($previousPeriodStart, $previousPeriodEnd) ?? false)
            ->sum('amount');

        return [
            'revenue_per_service' => $this->buildRevenueBreakdown(
                $paymentTransactions,
                    fn (object $transaction): string => $this->preferredServiceKey($transaction),
            ),
            'revenue_per_country' => $this->buildRevenueBreakdown(
                $paymentTransactions,
                    fn (object $transaction): string => $this->preferredCountryKey($transaction),
            ),
            'refund_rate' => [
                'percentage' => $paymentsTotal > 0
                    ? number_format(($refundsTotal / $paymentsTotal) * 100, 2, '.', '')
                    : '0.00',
                'refunds_total' => number_format($refundsTotal, 2, '.', ''),
                'payments_total' => number_format($paymentsTotal, 2, '.', ''),
            ],
            'average_order_value' => [
                'amount' => $ordersCount > 0
                    ? number_format($paymentsTotal / $ordersCount, 2, '.', '')
                    : '0.00',
                'orders_count' => $ordersCount,
            ],
            'transaction_mix' => $this->buildTransactionMix($mixTransactions),
            'monthly_growth' => [
                'current_period' => number_format($currentPeriodPayments, 2, '.', ''),
                'previous_period' => number_format($previousPeriodPayments, 2, '.', ''),
                'change_amount' => number_format($currentPeriodPayments - $previousPeriodPayments, 2, '.', ''),
                'change_percentage' => $previousPeriodPayments > 0
                    ? number_format((($currentPeriodPayments - $previousPeriodPayments) / $previousPeriodPayments) * 100, 2, '.', '')
                    : '0.00',
            ],
        ];
    }

    /**
     * Summarize revenue by a derived dimension.
     *
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @return array<int, array<string, int|string>>
     */
    private function buildRevenueBreakdown(Collection $transactions, callable $resolver): array
    {
        return $transactions
            ->groupBy(fn (object $transaction): string => $resolver($transaction))
            ->map(function ($group, string $value): array {
                return [
                    'key' => $value,
                    'count' => $group->count(),
                    'total' => number_format((float) $group->sum('amount'), 2, '.', ''),
                ];
            })
            ->sortBy('key')
            ->values()
            ->all();
    }

    /**
     * Summarize the main transaction type mix.
     *
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @return array<int, array<string, int|string>>
     */
    private function buildTransactionMix(Collection $transactions): array
    {
        $totalCount = $transactions->count();

        return $transactions
            ->groupBy(fn (object $transaction): string => $transaction->type)
            ->map(function ($group, string $type) use ($totalCount): array {
                return [
                    'key' => $type,
                    'count' => $group->count(),
                    'total' => number_format((float) $group->sum('amount'), 2, '.', ''),
                    'share' => $totalCount > 0
                        ? number_format(($group->count() / $totalCount) * 100, 2, '.', '')
                        : '0.00',
                ];
            })
            ->sortBy('key')
            ->values()
            ->all();
    }

    /**
     * Resolve a service bucket from the transaction source.
     */
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

    /**
     * Resolve a country bucket from the transaction source.
     */
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

    private function preferredServiceKey(object $transaction): string
    {
        $resolved = $this->resolveServiceKey((string) $transaction->source);

        return $resolved !== 'unknown' ? $resolved : ($transaction->service_type ?: 'unknown');
    }

    private function preferredCountryKey(object $transaction): string
    {
        $resolved = $this->resolveCountryKey((string) $transaction->source);

        return $resolved !== 'UNKNOWN' ? $resolved : ($transaction->customer_country ?: 'UNKNOWN');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Query\Builder
     */
    private function factsQuery(array $filters)
    {
        return DB::table('financial_transactions as ft')
            ->leftJoin('orders as o', 'ft.order_id', '=', 'o.id')
            ->leftJoin('users as customers', 'o.customer_id', '=', 'customers.id')
            ->select([
                'ft.id',
                'ft.type',
                'ft.source',
                'ft.currency',
                'ft.amount',
                'ft.created_at',
                'ft.order_id',
                'o.service_type',
                'o.provider_name',
                'customers.country as customer_country',
            ])
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('ft.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('ft.created_at', '<=', $date))
            ->when($filters['service_type'] ?? null, fn ($query, $serviceType) => $query->where('o.service_type', $serviceType))
            ->when($filters['country'] ?? null, fn ($query, $country) => $query->where('customers.country', $country))
            ->when($filters['provider_name'] ?? null, fn ($query, $provider) => $query->where('o.provider_name', 'like', "%{$provider}%"));
    }

    /**
     * Build an empty 7-day chart dataset for the dashboard fallback state.
     *
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    private function emptyChartData(): array
    {
        $startDate = now()->startOfDay()->subDays(6);

        $labels = collect(range(0, 6))
            ->map(fn (int $offset): string => $startDate->copy()->addDays($offset)->toDateString())
            ->values()
            ->all();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'key' => 'payments',
                    'data' => array_fill(0, 7, '0.00'),
                ],
                [
                    'label' => 'Refunds',
                    'key' => 'refunds',
                    'data' => array_fill(0, 7, '0.00'),
                ],
                [
                    'label' => 'Net',
                    'key' => 'net',
                    'data' => array_fill(0, 7, '0.00'),
                ],
            ],
        ];
    }
}