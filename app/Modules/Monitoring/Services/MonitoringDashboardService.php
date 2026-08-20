<?php

namespace App\Modules\Monitoring\Services;

use App\Models\ApplicationEvent;
use App\Models\Approval;
use App\Models\NotificationLog;
use App\Models\ProviderWallet;
use App\Models\Settlement;
use App\Models\SystemHealthCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonitoringDashboardService
{
    public function __construct(
        private readonly SystemHealthProbeService $healthProbeService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(bool $runLiveProbes = true): array
    {
        $services = $runLiveProbes
            ? $this->healthProbeService->probeAll()
            : $this->latestStoredProbes();

        $signals = $this->operationalSignals();
        $alerts = $this->buildAlerts($services, $signals);

        return [
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'services_ok' => collect($services)->where('status', SystemHealthCheck::STATUS_OK)->count(),
                'services_warn' => collect($services)->where('status', SystemHealthCheck::STATUS_WARN)->count(),
                'services_fail' => collect($services)->where('status', SystemHealthCheck::STATUS_FAIL)->count(),
                'active_alerts' => count($alerts),
                'critical_alerts' => collect($alerts)->where('severity', 'critical')->count(),
            ],
            'services' => $services,
            'signals' => $signals,
            'alerts' => $alerts,
            'recent_exceptions' => $this->recentEvents(ApplicationEvent::CATEGORY_EXCEPTION, 10),
            'recent_slow_requests' => $this->recentEvents(ApplicationEvent::CATEGORY_SLOW_REQUEST, 10),
            'recent_api_errors' => $this->recentEvents(ApplicationEvent::CATEGORY_API_ERROR, 10),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operationalSignals(): array
    {
        $pendingJobs = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0;
        $failedJobs = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;

        $emailFailures = NotificationLog::query()
            ->where('channel', 'email')
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $whatsappFailures = NotificationLog::query()
            ->where('channel', 'whatsapp')
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $smsFailures = NotificationLog::query()
            ->where('channel', 'sms')
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $exceptions1h = ApplicationEvent::query()
            ->where('category', ApplicationEvent::CATEGORY_EXCEPTION)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $slow1h = ApplicationEvent::query()
            ->where('category', ApplicationEvent::CATEGORY_SLOW_REQUEST)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $criticalBalance = (float) config('provider_health.alerts.wallet_critical_balance', 500);

        $walletAlerts = ProviderWallet::query()
            ->where('is_active', true)
            ->where(function ($query) use ($criticalBalance): void {
                $query
                    ->where('balance', '<', $criticalBalance)
                    ->orWhere(function ($thresholdQuery): void {
                        $thresholdQuery
                            ->whereNotNull('low_balance_threshold')
                            ->whereColumn('balance', '<=', 'low_balance_threshold');
                    });
            })
            ->count();

        $settlementAlerts = Settlement::query()
            ->whereIn('status', [
                Settlement::STATUS_DRAFT,
                Settlement::STATUS_OPEN,
                Settlement::STATUS_PENDING_REVIEW,
                Settlement::STATUS_REOPENED,
            ])
            ->whereDate('period_end', '<', now()->startOfMonth()->toDateString())
            ->count();

        $pendingApprovals = Approval::query()->where('status', Approval::STATUS_PENDING)->count();
        $failedApprovals = Approval::query()
            ->where('status', Approval::STATUS_FAILED)
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        return [
            'queue_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'exceptions_1h' => $exceptions1h,
            'slow_requests_1h' => $slow1h,
            'api_errors_1h' => ApplicationEvent::query()
                ->where('category', ApplicationEvent::CATEGORY_API_ERROR)
                ->where('created_at', '>=', now()->subHour())
                ->count(),
            'wallet_alerts' => $walletAlerts,
            'settlement_alerts' => $settlementAlerts,
            'email_failures_24h' => $emailFailures,
            'whatsapp_failures_24h' => $whatsappFailures,
            'sms_failures_24h' => $smsFailures,
            'pending_approvals' => $pendingApprovals,
            'failed_approvals_24h' => $failedApprovals,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @param  array<string, mixed>  $signals
     * @return array<int, array<string, mixed>>
     */
    private function buildAlerts(array $services, array $signals): array
    {
        $alerts = [];

        foreach ($services as $service) {
            if ($service['status'] === SystemHealthCheck::STATUS_FAIL) {
                $alerts[] = [
                    'severity' => 'critical',
                    'code' => 'service_'.$service['key'],
                    'message' => $service['label'].': '.$service['message'],
                ];
            } elseif ($service['status'] === SystemHealthCheck::STATUS_WARN) {
                $alerts[] = [
                    'severity' => 'warning',
                    'code' => 'service_'.$service['key'],
                    'message' => $service['label'].': '.$service['message'],
                ];
            }
        }

        if ($signals['failed_jobs'] >= (int) config('monitoring.queue.failed_critical', 20)) {
            $alerts[] = ['severity' => 'critical', 'code' => 'failed_jobs', 'message' => 'Failed jobs: '.$signals['failed_jobs']];
        } elseif ($signals['failed_jobs'] >= (int) config('monitoring.queue.failed_warn', 5)) {
            $alerts[] = ['severity' => 'warning', 'code' => 'failed_jobs', 'message' => 'Failed jobs: '.$signals['failed_jobs']];
        }

        if ($signals['wallet_alerts'] > 0) {
            $alerts[] = [
                'severity' => 'critical',
                'code' => 'wallet_alerts',
                'message' => $signals['wallet_alerts'].' provider wallet(s) below threshold',
            ];
        }

        if ($signals['settlement_alerts'] > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'code' => 'settlement_alerts',
                'message' => $signals['settlement_alerts'].' past settlement period(s) still open',
            ];
        }

        if ($signals['email_failures_24h'] > 0) {
            $alerts[] = [
                'severity' => $signals['email_failures_24h'] >= 10 ? 'critical' : 'warning',
                'code' => 'email_failures',
                'message' => 'Email failures (24h): '.$signals['email_failures_24h'],
            ];
        }

        if ($signals['whatsapp_failures_24h'] > 0) {
            $alerts[] = [
                'severity' => $signals['whatsapp_failures_24h'] >= 10 ? 'critical' : 'warning',
                'code' => 'whatsapp_failures',
                'message' => 'WhatsApp failures (24h): '.$signals['whatsapp_failures_24h'],
            ];
        }

        if ($signals['exceptions_1h'] > 0) {
            $alerts[] = [
                'severity' => $signals['exceptions_1h'] >= 5 ? 'critical' : 'warning',
                'code' => 'exceptions',
                'message' => 'Exceptions in last hour: '.$signals['exceptions_1h'],
            ];
        }

        return $alerts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentEvents(string $category, int $limit): array
    {
        return ApplicationEvent::query()
            ->where('category', $category)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (ApplicationEvent $event): array => [
                'id' => $event->id,
                'severity' => $event->severity,
                'source' => $event->source,
                'message' => $event->message,
                'created_at' => optional($event->created_at)?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestStoredProbes(): array
    {
        $keys = [
            'application', 'database', 'queue', 'cache', 'mail', 'sms', 'whatsapp',
            'booknow', 'insurance', 'esim', 'payment',
        ];

        $results = [];

        foreach ($keys as $key) {
            $row = SystemHealthCheck::query()
                ->where('check_key', $key)
                ->latest('id')
                ->first();

            $results[] = [
                'key' => $key,
                'label' => ucfirst($key),
                'status' => $row?->status ?? SystemHealthCheck::STATUS_WARN,
                'latency_ms' => $row?->latency_ms,
                'message' => $row?->message ?? 'No probe yet',
                'meta' => $row?->meta ?? [],
            ];
        }

        return $results;
    }
}
