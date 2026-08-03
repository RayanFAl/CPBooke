<?php

namespace App\Modules\ProviderHealth\Services;

use App\Models\Approval;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderApiEvent;
use App\Models\ProviderWallet;
use App\Models\Settlement;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProviderHealthService
{
    /**
     * @return array{
     *   generated_at: string,
     *   summary: array<string, mixed>,
     *   alerts: array<int, array<string, mixed>>,
     *   providers: array<int, array<string, mixed>>
     * }
     */
    public function dashboard(): array
    {
        $providers = Provider::query()
            ->with(['wallets' => fn ($query) => $query->where('is_active', true)->orderByDesc('id')])
            ->orderBy('name')
            ->get();

        $cards = $providers->map(fn (Provider $provider): array => $this->buildProviderCard($provider))->values();

        $alerts = $cards
            ->flatMap(fn (array $card): array => $card['alerts'])
            ->sortBy(fn (array $alert): int => $alert['severity'] === 'critical' ? 0 : 1)
            ->values()
            ->all();

        $scores = $cards->pluck('health_score');

        return [
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'providers_total' => $cards->count(),
                'excellent' => $cards->where('health_band', 'excellent')->count(),
                'watch' => $cards->where('health_band', 'watch')->count(),
                'critical' => $cards->where('health_band', 'critical')->count(),
                'active_alerts' => count($alerts),
                'critical_alerts' => collect($alerts)->where('severity', 'critical')->count(),
                'average_score' => $scores->isEmpty()
                    ? null
                    : round((float) $scores->avg(), 1),
            ],
            'alerts' => $alerts,
            'providers' => $cards->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildProviderCard(Provider $provider): array
    {
        $wallet = $this->primaryWallet($provider);
        $api = $this->apiMetrics($provider);
        $settlement = $this->settlementMetrics($provider);
        $pendingApprovals = $this->pendingApprovalsCount($provider);
        $failedOps = $this->failedOperationsCount($provider);
        $lastSync = $this->lastSuccessfulSync($provider, $api['last_success_at']);

        $components = [
            'api' => $this->scoreApi($api),
            'wallet' => $this->scoreWallet($wallet, $provider),
            'error_rate' => $this->scoreErrorRate($api['error_rate_1h']),
            'settlement' => $this->scoreSettlement($settlement),
            'pending' => $this->scorePending($pendingApprovals, $failedOps),
        ];

        $weights = config('provider_health.weights');
        $healthScore = 0.0;

        foreach ($components as $key => $score) {
            $healthScore += ($score * ((float) ($weights[$key] ?? 0)) / 100);
        }

        $healthScore = (int) round($healthScore);
        $band = $this->bandForScore($healthScore);
        $alerts = $this->buildAlerts($provider, $wallet, $api, $settlement, $pendingApprovals, $failedOps, $lastSync);

        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'key' => $provider->key,
            'status' => $provider->status,
            'integration_status' => $provider->integration_status,
            'health_score' => $healthScore,
            'health_band' => $band,
            'components' => $components,
            'api_status' => $api['status'],
            'avg_latency_ms' => $api['avg_latency_ms'],
            'error_rate_1h' => $api['error_rate_1h'],
            'error_rate_24h' => $api['error_rate_24h'],
            'events_1h' => $api['events_1h'],
            'events_24h' => $api['events_24h'],
            'wallet' => [
                'id' => $wallet?->id,
                'balance' => $wallet?->balance,
                'currency' => $wallet?->currency ?? $provider->default_currency,
                'low_balance_threshold' => $wallet?->low_balance_threshold,
                'is_low_balance' => $wallet?->isLowBalance() ?? false,
                'is_negative' => $wallet !== null && (float) $wallet->balance < 0,
            ],
            'credit_limit' => $provider->credit_limit,
            'credit_remaining' => $this->creditRemaining($provider, $wallet),
            'last_successful_sync_at' => $lastSync,
            'failed_operations_24h' => $failedOps,
            'settlement' => $settlement,
            'pending_approvals' => $pendingApprovals,
            'alerts' => $alerts,
        ];
    }

    private function primaryWallet(Provider $provider): ?ProviderWallet
    {
        $wallets = $provider->relationLoaded('wallets')
            ? $provider->wallets
            : $provider->wallets()->where('is_active', true)->get();

        return $wallets->firstWhere('environment', config('wallets.default_environment', 'production'))
            ?? $wallets->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function apiMetrics(Provider $provider): array
    {
        $hourAgo = now()->subMinutes((int) config('provider_health.windows.hour', 60));
        $dayAgo = now()->subMinutes((int) config('provider_health.windows.day', 1440));
        $offlineMinutes = (int) config('provider_health.alerts.api_offline_minutes', 10);

        $events24h = ProviderApiEvent::query()
            ->where('provider_id', $provider->id)
            ->where('created_at', '>=', $dayAgo)
            ->get(['success', 'latency_ms', 'created_at']);

        $events1h = $events24h->filter(fn (ProviderApiEvent $event): bool => $event->created_at >= $hourAgo);

        $errorRate1h = $this->errorRate($events1h);
        $errorRate24h = $this->errorRate($events24h);

        $latencies = $events1h->where('success', true)->pluck('latency_ms')->filter()->values();
        if ($latencies->isEmpty()) {
            $latencies = $events24h->where('success', true)->pluck('latency_ms')->filter()->values();
        }

        $avgLatency = $latencies->isEmpty() ? null : (int) round((float) $latencies->avg());

        $lastSuccess = $events24h->where('success', true)->sortByDesc('created_at')->first();
        $lastFailure = $events24h->where('success', false)->sortByDesc('created_at')->first();
        $lastSuccessAt = $lastSuccess?->created_at;

        $status = $this->deriveApiStatus(
            $provider,
            $lastSuccessAt,
            $errorRate1h,
            $avgLatency,
            $events1h->count(),
            $offlineMinutes,
        );

        return [
            'status' => $status,
            'avg_latency_ms' => $avgLatency,
            'error_rate_1h' => $errorRate1h,
            'error_rate_24h' => $errorRate24h,
            'events_1h' => $events1h->count(),
            'events_24h' => $events24h->count(),
            'last_success_at' => $lastSuccessAt?->toIso8601String(),
            'last_failure_at' => $lastFailure?->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, ProviderApiEvent>  $events
     */
    private function errorRate(Collection $events): ?float
    {
        if ($events->isEmpty()) {
            return null;
        }

        $failures = $events->where('success', false)->count();

        return round(($failures / $events->count()) * 100, 1);
    }

    private function deriveApiStatus(
        Provider $provider,
        ?Carbon $lastSuccessAt,
        ?float $errorRate1h,
        ?int $avgLatency,
        int $events1h,
        int $offlineMinutes,
    ): string {
        if (in_array($provider->integration_status, [Provider::INTEGRATION_ERROR, Provider::INTEGRATION_PAUSED], true)) {
            return 'offline';
        }

        if ($provider->integration_status === Provider::INTEGRATION_NOT_CONFIGURED) {
            return 'unknown';
        }

        $warnRate = (float) config('provider_health.alerts.error_rate_warn_percent', 5);
        $criticalRate = (float) config('provider_health.alerts.error_rate_critical_percent', 15);
        $latencyDegraded = (int) config('provider_health.alerts.latency_degraded_ms', 2000);

        if ($lastSuccessAt === null) {
            $proxy = Order::query()
                ->where('provider_id', $provider->id)
                ->max('updated_at');

            if ($proxy === null) {
                return $provider->integration_status === Provider::INTEGRATION_LIVE ? 'online' : 'unknown';
            }

            return Carbon::parse($proxy)->diffInMinutes(now()) > $offlineMinutes ? 'degraded' : 'online';
        }

        if ($lastSuccessAt->diffInMinutes(now()) >= $offlineMinutes && ($errorRate1h ?? 0) >= $criticalRate) {
            return 'offline';
        }

        if ($lastSuccessAt->diffInMinutes(now()) >= $offlineMinutes) {
            return 'degraded';
        }

        if (($errorRate1h ?? 0) >= $criticalRate && $events1h > 0) {
            return 'offline';
        }

        if (($errorRate1h ?? 0) >= $warnRate || ($avgLatency !== null && $avgLatency >= $latencyDegraded)) {
            return 'degraded';
        }

        return 'online';
    }

    /**
     * @return array<string, mixed>
     */
    private function settlementMetrics(Provider $provider): array
    {
        $latest = Settlement::query()
            ->where('provider_id', $provider->id)
            ->latest('id')
            ->first();

        $previousMonthStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $previousMonthEnd = now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $previousMonthClosed = Settlement::query()
            ->where('provider_id', $provider->id)
            ->where('status', Settlement::STATUS_CLOSED)
            ->whereDate('period_start', '<=', $previousMonthStart)
            ->whereDate('period_end', '>=', $previousMonthEnd)
            ->exists();

        $openPrevious = Settlement::query()
            ->where('provider_id', $provider->id)
            ->whereIn('status', [Settlement::STATUS_DRAFT, Settlement::STATUS_OPEN])
            ->whereDate('period_end', '<', now()->startOfMonth()->toDateString())
            ->exists();

        return [
            'latest_id' => $latest?->id,
            'latest_status' => $latest?->status,
            'latest_period_start' => optional($latest?->period_start)?->toDateString(),
            'latest_period_end' => optional($latest?->period_end)?->toDateString(),
            'latest_difference' => $latest?->difference,
            'latest_review_count' => $latest?->review_count ?? 0,
            'previous_month_closed' => $previousMonthClosed,
            'has_open_past_period' => $openPrevious,
        ];
    }

    private function pendingApprovalsCount(Provider $provider): int
    {
        $orderIds = Order::query()->where('provider_id', $provider->id)->select('id');
        $walletIds = ProviderWallet::query()->where('provider_id', $provider->id)->select('id');

        return Approval::query()
            ->where('status', Approval::STATUS_PENDING)
            ->where(function ($query) use ($orderIds, $walletIds): void {
                $query->where(function ($inner) use ($orderIds): void {
                    $inner->where('entity_type', Approval::ENTITY_ORDER)
                        ->whereIn('entity_id', $orderIds);
                })->orWhere(function ($inner) use ($walletIds): void {
                    $inner->where('entity_type', Approval::ENTITY_WALLET)
                        ->whereIn('entity_id', $walletIds);
                });
            })
            ->count();
    }

    private function failedOperationsCount(Provider $provider): int
    {
        $dayAgo = now()->subMinutes((int) config('provider_health.windows.day', 1440));

        $syncFailures = ProviderApiEvent::query()
            ->where('provider_id', $provider->id)
            ->where('success', false)
            ->where('created_at', '>=', $dayAgo)
            ->count();

        $failedOrders = Order::query()
            ->where('provider_id', $provider->id)
            ->where('status', Order::STATUS_FAILED)
            ->where('updated_at', '>=', $dayAgo)
            ->count();

        $failedApprovals = Approval::query()
            ->where('status', Approval::STATUS_FAILED)
            ->where('updated_at', '>=', $dayAgo)
            ->where(function ($query) use ($provider): void {
                $query->where(function ($inner) use ($provider): void {
                    $inner->where('entity_type', Approval::ENTITY_ORDER)
                        ->whereIn('entity_id', Order::query()->where('provider_id', $provider->id)->select('id'));
                })->orWhere(function ($inner) use ($provider): void {
                    $inner->where('entity_type', Approval::ENTITY_WALLET)
                        ->whereIn('entity_id', ProviderWallet::query()->where('provider_id', $provider->id)->select('id'));
                });
            })
            ->count();

        $queueFailures = 0;

        if (Schema::hasTable('failed_jobs')) {
            $queueFailures = (int) DB::table('failed_jobs')
                ->where('failed_at', '>=', $dayAgo)
                ->where(function ($query) use ($provider): void {
                    $query->where('payload', 'like', '%'.$provider->key.'%')
                        ->orWhere('payload', 'like', '%"provider_id":'.$provider->id.'%');
                })
                ->count();
        }

        return $syncFailures + $failedOrders + $failedApprovals + $queueFailures;
    }

    private function lastSuccessfulSync(Provider $provider, ?string $fromEvents): ?string
    {
        if ($fromEvents) {
            return $fromEvents;
        }

        $updatedAt = Order::query()
            ->where('provider_id', $provider->id)
            ->max('updated_at');

        return $updatedAt ? Carbon::parse($updatedAt)->toIso8601String() : null;
    }

    private function creditRemaining(Provider $provider, ?ProviderWallet $wallet): ?string
    {
        if ($provider->credit_limit === null) {
            return null;
        }

        $balance = $wallet ? (float) $wallet->balance : 0.0;
        $usedCredit = $balance < 0 ? abs($balance) : 0.0;
        $remaining = (float) $provider->credit_limit - $usedCredit;

        return number_format($remaining, 2, '.', '');
    }

    private function scoreApi(array $api): int
    {
        return match ($api['status']) {
            'online' => 100,
            'degraded' => 55,
            'offline' => 10,
            default => 70,
        };
    }

    private function scoreWallet(?ProviderWallet $wallet, Provider $provider): int
    {
        if ($wallet === null) {
            return 50;
        }

        $balance = (float) $wallet->balance;
        $critical = (float) config('provider_health.alerts.wallet_critical_balance', 500);
        $threshold = $wallet->low_balance_threshold !== null
            ? (float) $wallet->low_balance_threshold
            : $critical;

        if ($balance < 0) {
            return 15;
        }

        if ($balance <= $critical) {
            return 30;
        }

        if ($balance <= $threshold) {
            return 55;
        }

        if ($provider->credit_limit !== null) {
            $remaining = (float) ($this->creditRemaining($provider, $wallet) ?? 0);
            if ($remaining <= 0) {
                return 25;
            }
            if ($remaining < ((float) $provider->credit_limit * 0.2)) {
                return 60;
            }
        }

        return 100;
    }

    private function scoreErrorRate(?float $errorRate1h): int
    {
        if ($errorRate1h === null) {
            return 85;
        }

        $warn = (float) config('provider_health.alerts.error_rate_warn_percent', 5);
        $critical = (float) config('provider_health.alerts.error_rate_critical_percent', 15);

        if ($errorRate1h >= $critical) {
            return 20;
        }

        if ($errorRate1h >= $warn) {
            return 55;
        }

        if ($errorRate1h > 0) {
            return 85;
        }

        return 100;
    }

    private function scoreSettlement(array $settlement): int
    {
        if ($settlement['has_open_past_period']) {
            return 25;
        }

        if (! $settlement['previous_month_closed'] && now()->day >= 5) {
            return 45;
        }

        if (($settlement['latest_review_count'] ?? 0) > 0) {
            return 70;
        }

        if ($settlement['latest_status'] === Settlement::STATUS_CLOSED) {
            return 100;
        }

        if ($settlement['latest_status'] === Settlement::STATUS_OPEN) {
            return 80;
        }

        return 75;
    }

    private function scorePending(int $pendingApprovals, int $failedOps): int
    {
        $failedCritical = (int) config('provider_health.alerts.failed_ops_critical', 20);

        if ($failedOps >= $failedCritical) {
            return 15;
        }

        if ($failedOps >= (int) ($failedCritical / 2)) {
            return 40;
        }

        if ($pendingApprovals >= 10) {
            return 45;
        }

        if ($pendingApprovals >= 3) {
            return 70;
        }

        if ($pendingApprovals > 0 || $failedOps > 0) {
            return 85;
        }

        return 100;
    }

    private function bandForScore(int $score): string
    {
        $excellent = (int) config('provider_health.bands.excellent', 95);
        $watch = (int) config('provider_health.bands.watch', 75);

        if ($score >= $excellent) {
            return 'excellent';
        }

        if ($score >= $watch) {
            return 'watch';
        }

        return 'critical';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAlerts(
        Provider $provider,
        ?ProviderWallet $wallet,
        array $api,
        array $settlement,
        int $pendingApprovals,
        int $failedOps,
        ?string $lastSync,
    ): array {
        $alerts = [];
        $criticalWallet = (float) config('provider_health.alerts.wallet_critical_balance', 500);
        $offlineMinutes = (int) config('provider_health.alerts.api_offline_minutes', 10);
        $warnRate = (float) config('provider_health.alerts.error_rate_warn_percent', 5);
        $failedCritical = (int) config('provider_health.alerts.failed_ops_critical', 20);

        if ($wallet !== null && (float) $wallet->balance < $criticalWallet) {
            $alerts[] = $this->alert(
                $provider,
                'critical',
                'wallet_low',
                "Wallet below {$criticalWallet} ".($wallet->currency ?? 'LYD').' ('.$wallet->balance.')',
            );
        }

        if ($api['status'] === 'offline') {
            $alerts[] = $this->alert($provider, 'critical', 'api_offline', 'API appears offline or paused.');
        } elseif ($lastSync && Carbon::parse($lastSync)->diffInMinutes(now()) >= $offlineMinutes && $api['status'] !== 'online') {
            $alerts[] = $this->alert(
                $provider,
                'critical',
                'api_stale',
                "API has not responded successfully for {$offlineMinutes}+ minutes.",
            );
        }

        if (($api['error_rate_1h'] ?? 0) >= $warnRate) {
            $alerts[] = $this->alert(
                $provider,
                ($api['error_rate_1h'] ?? 0) >= (float) config('provider_health.alerts.error_rate_critical_percent', 15)
                    ? 'critical'
                    : 'warning',
                'error_rate',
                'Error rate '.$api['error_rate_1h'].'% in the last hour.',
            );
        }

        if ($settlement['has_open_past_period'] || (! $settlement['previous_month_closed'] && now()->day >= 5)) {
            $alerts[] = $this->alert(
                $provider,
                'warning',
                'settlement_open',
                'Settlement is not closed for the previous month.',
            );
        }

        if ($failedOps > $failedCritical) {
            $alerts[] = $this->alert(
                $provider,
                'critical',
                'failed_ops',
                "Failed operations in 24h: {$failedOps}.",
            );
        } elseif ($failedOps > 0) {
            $alerts[] = $this->alert(
                $provider,
                'warning',
                'failed_ops',
                "Failed operations in 24h: {$failedOps}.",
            );
        }

        if ($pendingApprovals >= 5) {
            $alerts[] = $this->alert(
                $provider,
                'warning',
                'pending_approvals',
                "{$pendingApprovals} pending approvals linked to this provider.",
            );
        }

        return $alerts;
    }

    /**
     * @return array<string, mixed>
     */
    private function alert(Provider $provider, string $severity, string $code, string $message): array
    {
        return [
            'provider_id' => $provider->id,
            'provider_name' => $provider->name,
            'provider_key' => $provider->key,
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
        ];
    }
}
