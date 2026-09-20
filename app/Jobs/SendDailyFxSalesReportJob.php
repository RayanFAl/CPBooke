<?php

namespace App\Jobs;

use App\Modules\Admin\ExchangeRates\Events\DailyFxSalesReportDue;
use App\Modules\Admin\ExchangeRates\Services\DailyFxSalesReportService;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class SendDailyFxSalesReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly ?string $reportDate = null,
    ) {
    }

    public function handle(
        DailyFxSalesReportService $reportService,
        NotificationService $notificationService,
    ): void {
        $day = $this->reportDate
            ? Carbon::parse($this->reportDate, DailyFxSalesReportService::REPORT_TIMEZONE)
            : null;

        $report = $reportService->build($day);

        $notificationService->dispatchForEvent(new DailyFxSalesReportDue($report));
    }
}
