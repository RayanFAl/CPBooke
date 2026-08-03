<?php

namespace App\Modules\Monitoring\Services;

use App\Models\Approval;
use App\Models\NotificationLog;
use App\Models\Provider;
use App\Models\ProviderApiEvent;
use App\Models\ProviderWallet;
use App\Models\Settlement;
use App\Models\SystemHealthCheck;
use App\Modules\Notifications\Services\NotificationChannelManager;
use App\Modules\Notifications\Support\NotificationChannels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemHealthProbeService
{
    public function __construct(
        private readonly NotificationChannelManager $notificationChannels,
    ) {
    }

    /**
     * Run all probes and persist results.
     *
     * @return array<int, array<string, mixed>>
     */
    public function runAndStore(): array
    {
        $results = $this->probeAll();

        foreach ($results as $result) {
            SystemHealthCheck::query()->create([
                'check_key' => $result['key'],
                'status' => $result['status'],
                'latency_ms' => $result['latency_ms'],
                'message' => $result['message'],
                'meta' => $result['meta'] ?? null,
                'created_at' => now(),
            ]);
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function probeAll(): array
    {
        return [
            $this->probeApplication(),
            $this->probeDatabase(),
            $this->probeQueue(),
            $this->probeCache(),
            $this->probeMail(),
            $this->probeSms(),
            $this->probeWhatsApp(),
            $this->probeProvider('booknow', 'BookNow', Provider::KEY_BOOKNOW),
            $this->probeProvider('insurance', 'Insurance', 'insurance'),
            $this->probeProvider('esim', 'eSIM', Provider::KEY_BOOKNOW_ESIM),
            $this->probePayment(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function probeApplication(): array
    {
        $started = microtime(true);

        return [
            'key' => 'application',
            'label' => 'Application',
            'status' => SystemHealthCheck::STATUS_OK,
            'latency_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
            'message' => 'Application process responding',
            'meta' => [
                'env' => config('app.env'),
                'debug' => (bool) config('app.debug'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function probeDatabase(): array
    {
        $started = microtime(true);

        try {
            DB::select('select 1');

            return [
                'key' => 'database',
                'label' => 'Database',
                'status' => SystemHealthCheck::STATUS_OK,
                'latency_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
                'message' => 'Database connection OK',
                'meta' => ['connection' => config('database.default')],
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'database',
                'label' => 'Database',
                'status' => SystemHealthCheck::STATUS_FAIL,
                'latency_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
                'message' => $exception->getMessage(),
                'meta' => [],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function probeQueue(): array
    {
        $started = microtime(true);
        $pending = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0;
        $failed = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;

        $warnPending = (int) config('monitoring.queue.pending_warn', 50);
        $critPending = (int) config('monitoring.queue.pending_critical', 200);
        $warnFailed = (int) config('monitoring.queue.failed_warn', 5);
        $critFailed = (int) config('monitoring.queue.failed_critical', 20);

        $status = SystemHealthCheck::STATUS_OK;
        $message = "Queue pending={$pending}, failed={$failed}";

        if ($failed >= $critFailed || $pending >= $critPending) {
            $status = SystemHealthCheck::STATUS_FAIL;
        } elseif ($failed >= $warnFailed || $pending >= $warnPending) {
            $status = SystemHealthCheck::STATUS_WARN;
        }

        return [
            'key' => 'queue',
            'label' => 'Queue',
            'status' => $status,
            'latency_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
            'message' => $message,
            'meta' => [
                'pending' => $pending,
                'failed' => $failed,
                'connection' => config('queue.default'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function probeCache(): array
    {
        $started = microtime(true);
        $key = 'monitoring:health-probe:'.uniqid('', true);

        try {
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            $ok = $value === 'ok';

            return [
                'key' => 'cache',
                'label' => 'Cache',
                'status' => $ok ? SystemHealthCheck::STATUS_OK : SystemHealthCheck::STATUS_FAIL,
                'latency_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
                'message' => $ok ? 'Cache read/write OK' : 'Cache probe mismatch',
                'meta' => ['store' => config('cache.default')],
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'cache',
                'label' => 'Cache',
                'status' => SystemHealthCheck::STATUS_FAIL,
                'latency_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
                'message' => $exception->getMessage(),
                'meta' => [],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function probeMail(): array
    {
        return $this->probeNotificationChannel(NotificationChannels::EMAIL, 'mail', 'Mail');
    }

    /**
     * @return array<string, mixed>
     */
    private function probeSms(): array
    {
        return $this->probeNotificationChannel(NotificationChannels::SMS, 'sms', 'SMS');
    }

    /**
     * @return array<string, mixed>
     */
    private function probeWhatsApp(): array
    {
        return $this->probeNotificationChannel(NotificationChannels::WHATSAPP, 'whatsapp', 'WhatsApp');
    }

    /**
     * @return array<string, mixed>
     */
    private function probeNotificationChannel(string $channel, string $key, string $label): array
    {
        $started = microtime(true);
        $statuses = collect($this->notificationChannels->statuses())->keyBy('channel');
        $row = $statuses->get($channel);
        $configured = (bool) ($row['configured'] ?? false);
        $failures24h = NotificationLog::query()
            ->where('channel', $channel)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $status = SystemHealthCheck::STATUS_OK;
        $message = $configured ? "{$label} configured" : "{$label} not configured";

        if (! $configured) {
            $status = SystemHealthCheck::STATUS_WARN;
        }

        if ($failures24h >= 10) {
            $status = SystemHealthCheck::STATUS_FAIL;
            $message .= "; {$failures24h} failures in 24h";
        } elseif ($failures24h >= 3) {
            $status = SystemHealthCheck::STATUS_WARN;
            $message .= "; {$failures24h} failures in 24h";
        }

        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'latency_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
            'message' => $message,
            'meta' => [
                'configured' => $configured,
                'provider' => $row['provider'] ?? null,
                'failures_24h' => $failures24h,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function probeProvider(string $key, string $label, string $providerKey): array
    {
        $started = microtime(true);
        $provider = Provider::query()->where('key', $providerKey)->first();

        if ($provider === null) {
            return [
                'key' => $key,
                'label' => $label,
                'status' => SystemHealthCheck::STATUS_WARN,
                'latency_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
                'message' => "{$label} provider not configured yet",
                'meta' => ['provider_key' => $providerKey],
            ];
        }

        if (in_array($provider->integration_status, [Provider::INTEGRATION_ERROR, Provider::INTEGRATION_PAUSED], true)) {
            return [
                'key' => $key,
                'label' => $label,
                'status' => SystemHealthCheck::STATUS_FAIL,
                'latency_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
                'message' => "{$label} integration status: {$provider->integration_status}",
                'meta' => ['provider_id' => $provider->id],
            ];
        }

        $failures1h = ProviderApiEvent::query()
            ->where('provider_id', $provider->id)
            ->where('success', false)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $status = SystemHealthCheck::STATUS_OK;
        $message = "{$label} live";

        if ($failures1h >= 10) {
            $status = SystemHealthCheck::STATUS_FAIL;
            $message = "{$label} has {$failures1h} API failures in 1h";
        } elseif ($failures1h >= 3) {
            $status = SystemHealthCheck::STATUS_WARN;
            $message = "{$label} has {$failures1h} API failures in 1h";
        }

        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'latency_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
            'message' => $message,
            'meta' => [
                'provider_id' => $provider->id,
                'integration_status' => $provider->integration_status,
                'failures_1h' => $failures1h,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function probePayment(): array
    {
        $started = microtime(true);
        $failedApprovals = Approval::query()
            ->where('status', Approval::STATUS_FAILED)
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        $lowWallets = ProviderWallet::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereColumn('balance', '<=', 'low_balance_threshold')
                    ->orWhere('balance', '<', 0);
            })
            ->count();

        $openSettlements = Settlement::query()
            ->whereIn('status', [Settlement::STATUS_DRAFT, Settlement::STATUS_OPEN])
            ->whereDate('period_end', '<', now()->startOfMonth()->toDateString())
            ->count();

        $status = SystemHealthCheck::STATUS_OK;
        $message = 'Payment / ledger path healthy';

        if ($failedApprovals >= 5 || $lowWallets >= 3) {
            $status = SystemHealthCheck::STATUS_FAIL;
            $message = 'Payment path needs attention';
        } elseif ($failedApprovals > 0 || $lowWallets > 0 || $openSettlements > 0) {
            $status = SystemHealthCheck::STATUS_WARN;
            $message = 'Payment path has warnings';
        }

        return [
            'key' => 'payment',
            'label' => 'Payment',
            'status' => $status,
            'latency_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
            'message' => $message,
            'meta' => [
                'failed_approvals_24h' => $failedApprovals,
                'low_wallets' => $lowWallets,
                'open_past_settlements' => $openSettlements,
            ],
        ];
    }
}
