<?php

namespace App\Modules\Admin\ExchangeRates\Services;

use App\Models\ExchangeRate;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailyFxSalesReportService
{
    public const REPORT_TIMEZONE = 'Africa/Tripoli';

    /**
     * Build the daily FX buy + sales report for one calendar day in Libya time.
     *
     * @return array{
     *     report_date: string,
     *     timezone: string,
     *     generated_at: string,
     *     buy_rates: list<array{currency_code: string, buy_rate_to_lyd: string|null, updated_at: string|null}>,
     *     sales_by_currency: list<array{currency: string, orders_count: int, total_amount: string}>,
     *     totals: array{orders_count: int, currencies: list<string>},
     *     orders: list<array<string, mixed>>,
     *     summary_line: string,
     *     usd_buy_rate: string,
     *     eur_buy_rate: string,
     *     sales_summary: string
     * }
     */
    public function build(?Carbon $day = null): array
    {
        $reportDay = $this->resolveReportDay($day);
        $start = $reportDay->copy()->startOfDay();
        $end = $reportDay->copy()->endOfDay();

        $buyRates = $this->buyRates();
        $orders = $this->paidOrdersForDay($start, $end);
        $salesByCurrency = $this->salesByCurrency($orders);

        $usdBuy = $this->rateValue($buyRates, ExchangeRate::CURRENCY_USD);
        $eurBuy = $this->rateValue($buyRates, ExchangeRate::CURRENCY_EUR);
        $salesSummary = $this->formatSalesSummary($salesByCurrency);

        return [
            'report_date' => $reportDay->toDateString(),
            'timezone' => self::REPORT_TIMEZONE,
            'generated_at' => now()->toIso8601String(),
            'buy_rates' => $buyRates,
            'sales_by_currency' => $salesByCurrency,
            'totals' => [
                'orders_count' => $orders->count(),
                'currencies' => array_values(array_map(
                    static fn (array $row): string => $row['currency'],
                    $salesByCurrency,
                )),
            ],
            'orders' => $orders
                ->map(fn (Order $order): array => $this->serializeOrder($order))
                ->values()
                ->all(),
            'summary_line' => sprintf(
                'USD buy %s LYD · EUR buy %s LYD · %s',
                $usdBuy,
                $eurBuy,
                $salesSummary,
            ),
            'usd_buy_rate' => $usdBuy,
            'eur_buy_rate' => $eurBuy,
            'sales_summary' => $salesSummary,
        ];
    }

    public function defaultReportDate(): string
    {
        return $this->resolveReportDay(null)->toDateString();
    }

    public function resolveReportDay(?Carbon $day = null): Carbon
    {
        if ($day instanceof Carbon) {
            return $day->copy()->timezone(self::REPORT_TIMEZONE)->startOfDay();
        }

        return Carbon::now(self::REPORT_TIMEZONE)->subDay()->startOfDay();
    }

    /**
     * @return list<array{currency_code: string, buy_rate_to_lyd: string|null, updated_at: string|null}>
     */
    private function buyRates(): array
    {
        $rates = ExchangeRate::query()
            ->whereIn('currency_code', ExchangeRate::EDITABLE_CURRENCIES)
            ->where('is_active', true)
            ->get()
            ->keyBy('currency_code');

        return array_map(
            static function (string $code) use ($rates): array {
                $rate = $rates->get($code);

                return [
                    'currency_code' => $code,
                    'buy_rate_to_lyd' => $rate
                        ? number_format((float) $rate->buy_rate_to_lyd, 8, '.', '')
                        : null,
                    'updated_at' => $rate?->updated_at?->toIso8601String(),
                ];
            },
            ExchangeRate::EDITABLE_CURRENCIES,
        );
    }

    /**
     * @return Collection<int, Order>
     */
    private function paidOrdersForDay(Carbon $start, Carbon $end): Collection
    {
        return Order::query()
            ->with('customer:id,name,full_name,email')
            ->where('payment_status', Order::PAYMENT_STATUS_PAID)
            ->whereBetween('updated_at', [
                $start->copy()->utc()->toDateTimeString(),
                $end->copy()->utc()->toDateTimeString(),
            ])
            ->orderByDesc('id')
            ->limit(500)
            ->get();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{currency: string, orders_count: int, total_amount: string}>
     */
    private function salesByCurrency(Collection $orders): array
    {
        $grouped = $orders
            ->groupBy(fn (Order $order): string => strtoupper((string) ($order->currency ?: Order::DEFAULT_CURRENCY)))
            ->map(function (Collection $rows, string $currency): array {
                $total = $rows->sum(function (Order $order): float {
                    $selling = $order->selling_price;

                    if ($selling !== null && (float) $selling > 0) {
                        return (float) $selling;
                    }

                    return (float) $order->total_amount;
                });

                return [
                    'currency' => $currency,
                    'orders_count' => $rows->count(),
                    'total_amount' => number_format($total, 2, '.', ''),
                ];
            });

        $preferred = ['LYD', 'USD', 'EUR'];
        $ordered = [];

        foreach ($preferred as $currency) {
            if ($grouped->has($currency)) {
                $ordered[] = $grouped->get($currency);
            }
        }

        foreach ($grouped->keys()->sort()->all() as $currency) {
            if (! in_array($currency, $preferred, true)) {
                $ordered[] = $grouped->get($currency);
            }
        }

        return $ordered;
    }

    /**
     * @param  list<array{currency_code: string, buy_rate_to_lyd: string|null, updated_at: string|null}>  $buyRates
     */
    private function rateValue(array $buyRates, string $code): string
    {
        foreach ($buyRates as $rate) {
            if (($rate['currency_code'] ?? null) === $code) {
                return $rate['buy_rate_to_lyd'] ?? '—';
            }
        }

        return '—';
    }

    /**
     * @param  list<array{currency: string, orders_count: int, total_amount: string}>  $sales
     */
    private function formatSalesSummary(array $sales): string
    {
        if ($sales === []) {
            return '0 paid orders';
        }

        return implode(' · ', array_map(
            static fn (array $row): string => sprintf(
                '%s: %s (%d)',
                $row['currency'],
                $row['total_amount'],
                $row['orders_count'],
            ),
            $sales,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order): array
    {
        $amount = $order->selling_price !== null && (float) $order->selling_price > 0
            ? (string) $order->selling_price
            : (string) $order->total_amount;

        return [
            'id' => $order->id,
            'booking_reference' => $order->booking_reference,
            'customer_name' => $order->customer?->full_name
                ?: $order->customer?->name
                ?: '—',
            'service_type' => $order->service_type,
            'currency' => strtoupper((string) ($order->currency ?: Order::DEFAULT_CURRENCY)),
            'amount' => number_format((float) $amount, 2, '.', ''),
            'payment_method' => $order->payment_method,
            'status' => $order->status,
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }
}
