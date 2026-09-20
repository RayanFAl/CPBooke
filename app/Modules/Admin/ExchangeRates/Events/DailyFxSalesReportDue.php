<?php

namespace App\Modules\Admin\ExchangeRates\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DailyFxSalesReportDue
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array{
     *     report_date: string,
     *     timezone?: string,
     *     summary_line?: string,
     *     usd_buy_rate?: string,
     *     eur_buy_rate?: string,
     *     sales_summary?: string,
     *     totals?: array{orders_count?: int}
     * }  $report
     */
    public function __construct(
        public readonly array $report,
    ) {
    }

    public function reportDate(): string
    {
        return (string) ($this->report['report_date'] ?? '');
    }

    public function usdBuyRate(): string
    {
        return (string) ($this->report['usd_buy_rate'] ?? '—');
    }

    public function eurBuyRate(): string
    {
        return (string) ($this->report['eur_buy_rate'] ?? '—');
    }

    public function salesSummary(): string
    {
        return (string) ($this->report['sales_summary'] ?? '0 paid orders');
    }

    public function summaryLine(): string
    {
        return (string) ($this->report['summary_line'] ?? '');
    }

    public function ordersCount(): int
    {
        return (int) ($this->report['totals']['orders_count'] ?? 0);
    }
}
