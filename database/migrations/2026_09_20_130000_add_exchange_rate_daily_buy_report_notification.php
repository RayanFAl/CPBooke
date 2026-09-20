<?php

use App\Models\NotificationTemplate;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Notifications\Support\NotificationLocales;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $existing = NotificationTemplate::query()
            ->where('code', 'EXCHANGE_RATE_DAILY_BUY_REPORT')
            ->first();

        $payload = [
            'name' => 'Daily FX Buy + Sales Report',
            'category' => 'general',
            'description' => 'Daily admin digest of USD/EUR buy rates with paid booking sales, linking to the admin PDF report.',
            'subject' => 'Daily FX report {report_date}',
            'body' => 'USD buy {usd_buy_rate} LYD · EUR buy {eur_buy_rate} LYD. Sales: {sales_summary}. Open the admin report for PDF.',
            'channels' => [NotificationChannels::EMAIL, NotificationChannels::IN_APP],
            'variables' => [
                'report_date',
                'usd_buy_rate',
                'eur_buy_rate',
                'sales_summary',
                'orders_count',
                'deep_link',
            ],
            'translations' => [
                NotificationLocales::AR => [
                    'name' => 'تقرير يومي لأسعار الشراء والمبيعات',
                    'subject' => 'تقرير الصرف اليومي {report_date}',
                    'body' => 'شراء الدولار {usd_buy_rate} د.ل · شراء اليورو {eur_buy_rate} د.ل. المبيعات: {sales_summary}. افتح تقرير الأدمن للـ PDF.',
                ],
            ],
            'version' => 1,
            'is_active' => true,
        ];

        if ($existing) {
            $existing->forceFill($payload)->save();

            return;
        }

        NotificationTemplate::query()->create(array_merge($payload, [
            'code' => 'EXCHANGE_RATE_DAILY_BUY_REPORT',
        ]));
    }

    public function down(): void
    {
        NotificationTemplate::query()
            ->where('code', 'EXCHANGE_RATE_DAILY_BUY_REPORT')
            ->delete();
    }
};
