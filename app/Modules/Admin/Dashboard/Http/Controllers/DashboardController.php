<?php

namespace App\Modules\Admin\Dashboard\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Modules\Admin\Dashboard\Services\AppPulseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __construct(
        private readonly AppPulseService $appPulseService,
    ) {}

    /**
     * Display the admin dashboard shell.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('admin/dashboard/pages/Index', [
            'dashboard' => $this->dashboardPayload(),
        ]);
    }

    /**
     * Build a schema-safe admin dashboard payload.
     *
     * @return array<string, mixed>
     */
    private function dashboardPayload(): array
    {
        $today = Carbon::today();
        $rangeStart = $today->copy()->subDays(6)->startOfDay();

        $labels = collect(range(0, 6))
            ->map(fn (int $offset): array => [
                'date' => $rangeStart->copy()->addDays($offset)->toDateString(),
                'label' => $rangeStart->copy()->addDays($offset)->format('D'),
            ]);

        $hasOrdersTable = Schema::hasTable('orders');
        $hasUsersTable = Schema::hasTable('users');
        $hasSupportTicketsTable = Schema::hasTable('support_tickets');
        $hasOrderPaymentStatusColumn = $hasOrdersTable && Schema::hasColumn('orders', 'payment_status');
        $hasOrderServiceTypeColumn = $hasOrdersTable && Schema::hasColumn('orders', 'service_type');
        $hasOrderBookingReferenceColumn = $hasOrdersTable && Schema::hasColumn('orders', 'booking_reference');
        $hasOrderCurrencyColumn = $hasOrdersTable && Schema::hasColumn('orders', 'currency');
        $hasOrderTotalAmountColumn = $hasOrdersTable && Schema::hasColumn('orders', 'total_amount');

        $totalRevenue = 0;
        $totalOrders = 0;
        $processingOrders = 0;
        $completedOrders = 0;
        $failedOrders = 0;
        $customers = 0;
        $newCustomersThisWeek = 0;
        $openTickets = 0;
        $urgentTickets = 0;

        $orderTrend = $labels->map(fn (array $point): array => [
            'label' => $point['label'],
            'value' => 0,
        ])->all();

        $revenueTrend = $labels->map(fn (array $point): array => [
            'label' => $point['label'],
            'value' => 0,
        ])->all();

        $statusBreakdown = [];
        $serviceBreakdown = [];
        $supportBreakdown = [];
        $latestOrders = [];

        if ($hasOrdersTable) {
            $totalOrders = Order::query()->count();
            $processingOrders = Order::query()->whereIn('status', ['processing', 'pending_payment', 'paid'])->count();
            $completedOrders = Order::query()->whereIn('status', ['confirmed', 'completed'])->count();
            $failedOrders = Order::query()->whereIn('status', ['failed', 'cancelled', 'refunded'])->count();
            $totalRevenueQuery = Order::query();

            if ($hasOrderPaymentStatusColumn) {
                $totalRevenueQuery->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded']);
            }

            $totalRevenue = $hasOrderTotalAmountColumn
                ? (float) $totalRevenueQuery->sum('total_amount')
                : 0;

            $dailyOrders = Order::query()
                ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
                ->where('created_at', '>=', $rangeStart)
                ->groupBy('day')
                ->pluck('aggregate', 'day');

            $dailyRevenue = collect();

            if ($hasOrderTotalAmountColumn) {
                $dailyRevenueQuery = Order::query()
                    ->selectRaw('DATE(created_at) as day, COALESCE(SUM(total_amount), 0) as aggregate')
                    ->where('created_at', '>=', $rangeStart);

                if ($hasOrderPaymentStatusColumn) {
                    $dailyRevenueQuery->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded']);
                }

                $dailyRevenue = $dailyRevenueQuery
                    ->groupBy('day')
                    ->pluck('aggregate', 'day');
            }

            $orderTrend = $labels->map(fn (array $point): array => [
                'label' => $point['label'],
                'value' => (int) ($dailyOrders[$point['date']] ?? 0),
            ])->all();

            $revenueTrend = $labels->map(fn (array $point): array => [
                'label' => $point['label'],
                'value' => (float) ($dailyRevenue[$point['date']] ?? 0),
            ])->all();

            $statusBreakdown = Order::query()
                ->select('status', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('status')
                ->orderByDesc('aggregate')
                ->limit(5)
                ->get()
                ->map(fn (object $row): array => [
                    'label' => (string) $row->status,
                    'value' => (int) $row->aggregate,
                ])
                ->all();

            if ($hasOrderServiceTypeColumn) {
                $serviceBreakdown = Order::query()
                    ->select('service_type', DB::raw('COUNT(*) as aggregate'))
                    ->groupBy('service_type')
                    ->orderByDesc('aggregate')
                    ->limit(4)
                    ->get()
                    ->map(fn (object $row): array => [
                        'label' => (string) ($row->service_type ?: 'unknown'),
                        'value' => (int) $row->aggregate,
                    ])
                    ->all();
            }

            $latestOrders = Order::query()
                ->with('customer:id,name,full_name,email')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'reference' => $hasOrderBookingReferenceColumn
                        ? ($order->booking_reference ?: ('#'.$order->id))
                        : ('#'.$order->id),
                    'customer_name' => $order->customer?->full_name ?: $order->customer?->name ?: 'Unknown customer',
                    'status' => $order->status,
                    'payment_status' => $hasOrderPaymentStatusColumn ? $order->payment_status : null,
                    'amount' => $hasOrderTotalAmountColumn ? (float) ($order->total_amount ?? 0) : 0,
                    'currency' => $hasOrderCurrencyColumn
                        ? ($order->currency ?: \App\Support\Platform\PlatformSettings::defaultCurrency())
                        : \App\Support\Platform\PlatformSettings::defaultCurrency(),
                    'created_at' => optional($order->created_at)?->toIso8601String(),
                ])
                ->all();
        }

        if ($hasUsersTable) {
            $customers = User::query()->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)->count();
            $newCustomersThisWeek = User::query()
                ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)
                ->where('created_at', '>=', $rangeStart)
                ->count();
        }

        if ($hasSupportTicketsTable) {
            $openTickets = DB::table('support_tickets')
                ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
                ->count();

            $urgentTickets = DB::table('support_tickets')
                ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
                ->whereIn('priority', ['high', 'urgent'])
                ->count();

            $supportBreakdown = DB::table('support_tickets')
                ->select('status', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('status')
                ->orderByDesc('aggregate')
                ->limit(5)
                ->get()
                ->map(fn (object $row): array => [
                    'label' => (string) $row->status,
                    'value' => (int) $row->aggregate,
                ])
                ->all();
        }

        $completionRate = $totalOrders > 0
            ? round(($completedOrders / $totalOrders) * 100, 1)
            : 0;

        return [
            'generated_at' => now()->toIso8601String(),
            'app_pulse' => $this->appPulseService->dashboardPayload($rangeStart, $labels),
            'overview' => [
                [
                    'key' => 'revenue',
                    'label' => 'Captured revenue',
                    'value' => $totalRevenue,
                    'format' => 'currency',
                    'helper' => ! $hasOrderTotalAmountColumn
                        ? 'Revenue is unavailable because total amount is not present in this schema.'
                        : ($hasOrderPaymentStatusColumn
                        ? 'Paid and refunded order volume combined.'
                        : 'Total booked volume from orders because payment status is not available in this schema.'),
                    'accent' => 'emerald',
                ],
                [
                    'key' => 'orders',
                    'label' => 'Total orders',
                    'value' => $totalOrders,
                    'format' => 'number',
                    'helper' => $processingOrders.' still in active operational flow.',
                    'accent' => 'sky',
                ],
                [
                    'key' => 'customers',
                    'label' => 'Customers',
                    'value' => $customers,
                    'format' => 'number',
                    'helper' => $newCustomersThisWeek.' new customers in the last 7 days.',
                    'accent' => 'amber',
                ],
                [
                    'key' => 'tickets',
                    'label' => 'Open tickets',
                    'value' => $openTickets,
                    'format' => 'number',
                    'helper' => $urgentTickets.' require high-priority follow-up.',
                    'accent' => 'rose',
                ],
            ],
            'spotlights' => [
                [
                    'label' => 'Completion rate',
                    'value' => $completionRate,
                    'format' => 'percent',
                    'tone' => $completionRate >= 65 ? 'good' : ($completionRate >= 40 ? 'warn' : 'critical'),
                ],
                [
                    'label' => 'Orders at risk',
                    'value' => $failedOrders,
                    'format' => 'number',
                    'tone' => $failedOrders <= 5 ? 'good' : ($failedOrders <= 15 ? 'warn' : 'critical'),
                ],
                [
                    'label' => 'Live operations queue',
                    'value' => $processingOrders,
                    'format' => 'number',
                    'tone' => $processingOrders <= 20 ? 'good' : ($processingOrders <= 50 ? 'warn' : 'critical'),
                ],
            ],
            'charts' => [
                'orders_trend' => $orderTrend,
                'revenue_trend' => $revenueTrend,
                'status_breakdown' => $statusBreakdown,
                'service_breakdown' => $serviceBreakdown,
                'support_breakdown' => $supportBreakdown,
            ],
            'latest_orders' => $latestOrders,
        ];
    }
}