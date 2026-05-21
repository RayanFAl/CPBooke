<?php

namespace App\Modules\Admin\Governance\Services;

use App\Models\FinancialTransaction;
use App\Models\LoyaltyHistory;
use App\Models\NotificationLog;
use App\Models\RbacAuditLog;
use App\Modules\Admin\Finance\Services\FinancialConsistencyService;
use App\Modules\Admin\Governance\DTO\GovernanceSnapshot;
use App\Modules\Notifications\Services\NotificationChannelManager;
use App\Support\Rbac\RbacAuthorizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class GovernanceService
{
    public function __construct(
        private readonly FinancialConsistencyService $financialConsistencyService,
        private readonly NotificationChannelManager $notificationChannelManager,
        private readonly RbacAuthorizer $rbacAuthorizer,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function snapshot(array $filters = []): GovernanceSnapshot
    {
        $this->rbacAuthorizer->authorize('governance.view');

        $normalizedFilters = $this->normalizeFilters($filters);
        $cacheKey = $this->cacheKey($normalizedFilters);

        $cachedSnapshot = Cache::get($cacheKey);

        if ($cachedSnapshot instanceof GovernanceSnapshot) {
            return $cachedSnapshot;
        }

        if (is_array($cachedSnapshot)) {
            return $this->makeSnapshot($cachedSnapshot);
        }

        if ($cachedSnapshot !== null) {
            Cache::forget($cacheKey);
        }

        $snapshotData = $this->buildSnapshotData($normalizedFilters);

        Cache::put($cacheKey, $snapshotData, now()->addMinutes(10));

        return $this->makeSnapshot($snapshotData);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildSnapshotData(array $filters): array
    {
        return [
            'rbac' => $this->rbacSection($filters),
            'finance' => $this->financeSection($filters),
            'notifications' => $this->notificationsSection($filters),
            'loyalty' => $this->loyaltySection($filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshotData
     */
    private function makeSnapshot(array $snapshotData): GovernanceSnapshot
    {
        return new GovernanceSnapshot(
            rbac: is_array($snapshotData['rbac'] ?? null) ? $snapshotData['rbac'] : [],
            finance: is_array($snapshotData['finance'] ?? null) ? $snapshotData['finance'] : [],
            notifications: is_array($snapshotData['notifications'] ?? null) ? $snapshotData['notifications'] : [],
            loyalty: is_array($snapshotData['loyalty'] ?? null) ? $snapshotData['loyalty'] : [],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'date_from' => $filters['date_from'] ?? now()->subDay()->toDateString(),
            'date_to' => $filters['date_to'] ?? now()->toDateString(),
            'module' => $filters['module'] ?? 'rbac',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function cacheKey(array $filters): string
    {
        return 'governance.snapshot.'.md5(json_encode([
            'filters' => $filters,
            'rbac_last' => $this->latestUpdatedAt(RbacAuditLog::class),
            'notifications_last' => $this->latestUpdatedAt(NotificationLog::class),
            'loyalty_last' => $this->latestUpdatedAt(LoyaltyHistory::class, 'changed_at'),
            'finance_last' => $this->latestUpdatedAt(FinancialTransaction::class),
        ]) ?: '{}');
    }

    private function latestUpdatedAt(string $modelClass, string $column = 'updated_at'): ?string
    {
        $table = (new $modelClass())->getTable();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return null;
        }

        $value = $modelClass::query()->max($column);

        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        return Carbon::parse((string) $value)->toDateTimeString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function rbacSection(array $filters): array
    {
        if (! Schema::hasTable('rbac_audit_logs')) {
            return [
                'kpi' => [
                    'label' => 'RBAC events',
                    'value' => 0,
                    'delta' => '0 in last 24h',
                    'status' => 'idle',
                ],
                'summary_24h' => [
                    'events' => 0,
                    'unique_users' => 0,
                    'sensitive_actions' => 0,
                ],
                'events' => [],
            ];
        }

        $query = RbacAuditLog::query()
            ->with('user:id,name,full_name,email')
            ->when($filters['date_from'], fn ($builder, $date) => $builder->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn ($builder, $date) => $builder->whereDate('created_at', '<=', $date));

        $events = (clone $query)
            ->latest('created_at')
            ->limit(20)
            ->get();

        $lastDayQuery = RbacAuditLog::query()->where('created_at', '>=', now()->subDay());

        return [
            'kpi' => [
                'label' => 'RBAC events',
                'value' => $events->count(),
                'delta' => $lastDayQuery->count().' in last 24h',
                'status' => $lastDayQuery->count() > 0 ? 'active' : 'idle',
            ],
            'summary_24h' => [
                'events' => $lastDayQuery->count(),
                'unique_users' => (clone $lastDayQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
                'sensitive_actions' => (clone $lastDayQuery)->whereIn('action', [
                    'finance.reconcile.executed',
                    'notifications.template.updated',
                    'loyalty.rule.updated',
                    'loyalty.benefit.updated',
                ])->count(),
            ],
            'events' => $events->map(fn (RbacAuditLog $log): array => [
                'id' => $log->id,
                'actor' => $log->user?->full_name ?: $log->user?->name ?: $log->user?->email ?: 'System',
                'permission' => $log->permission,
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'status' => str_contains((string) $log->action, 'updated') || str_contains((string) $log->action, 'executed') ? 'sensitive' : 'observed',
                'created_at' => $log->created_at?->toDateTimeString(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function financeSection(array $filters): array
    {
        $summary = $this->financialConsistencyService->summarize([
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ]);

        $currentWindow = $this->paymentsBetween($filters['date_from'], $filters['date_to']);
        $previousWindow = $this->previousWindowPayments($filters['date_from'], $filters['date_to']);
        $driftAmount = $currentWindow - $previousWindow;
        $driftPercentage = $previousWindow > 0
            ? (($driftAmount / $previousWindow) * 100)
            : ($currentWindow > 0 ? 100.0 : 0.0);

        $lastReconcile = Schema::hasTable('rbac_audit_logs')
            ? RbacAuditLog::query()
                ->where('action', 'finance.reconcile.executed')
                ->latest('created_at')
                ->first()
            : null;

        return [
            'kpi' => [
                'label' => 'Finance anomalies',
                'value' => $summary['counts']['total'],
                'delta' => number_format($driftPercentage, 2, '.', '').'% revenue drift',
                'status' => $summary['counts']['total'] > 0 ? 'warning' : 'healthy',
            ],
            'summary_24h' => [
                'reconciliation_status' => $lastReconcile ? 'recorded' : 'not_run',
                'critical_anomalies' => collect($summary['items'])->where('severity', 'critical')->count(),
                'revenue_drift' => number_format($driftAmount, 2, '.', ''),
            ],
            'events' => collect($summary['items'])
                ->take(20)
                ->map(fn (array $item): array => [
                    'id' => ($item['transaction']['id'] ?? $item['order']['id'] ?? uniqid('finance_', false)),
                    'code' => $item['code'],
                    'message' => $item['message'],
                    'severity' => $item['severity'],
                    'reference' => $item['order']['booking_reference'] ?? ($item['transaction']['id'] ?? 'unknown'),
                    'created_at' => $item['detected_at'],
                ])
                ->all(),
            'last_reconcile' => $lastReconcile ? [
                'actor' => $lastReconcile->user?->full_name ?: $lastReconcile->user?->name ?: $lastReconcile->user?->email ?: 'System',
                'created_at' => $lastReconcile->created_at?->toDateTimeString(),
            ] : null,
        ];
    }

    private function paymentsBetween(string $dateFrom, string $dateTo): float
    {
        if (! Schema::hasTable('financial_transactions')) {
            return 0.0;
        }

        return (float) FinancialTransaction::query()
            ->where('type', FinancialTransaction::TYPE_PAYMENT)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->sum('amount');
    }

    private function previousWindowPayments(string $dateFrom, string $dateTo): float
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();
        $days = max(1, $from->diffInDays($to) + 1);
        $previousFrom = $from->copy()->subDays($days);
        $previousTo = $from->copy()->subDay()->endOfDay();

        return (float) FinancialTransaction::query()
            ->where('type', FinancialTransaction::TYPE_PAYMENT)
            ->whereBetween('created_at', [$previousFrom, $previousTo])
            ->sum('amount');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function notificationsSection(array $filters): array
    {
        if (! Schema::hasTable('notification_logs')) {
            return [
                'kpi' => [
                    'label' => 'Notification failures',
                    'value' => 0,
                    'delta' => '0 failed in last 24h',
                    'status' => 'idle',
                ],
                'summary_24h' => [
                    'sent' => 0,
                    'failed' => 0,
                    'success_rate' => '0.00',
                ],
                'events' => [],
                'channels' => [],
            ];
        }

        $query = NotificationLog::query()
            ->with('user:id,name,full_name,email')
            ->when($filters['date_from'], fn ($builder, $date) => $builder->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn ($builder, $date) => $builder->whereDate('created_at', '<=', $date));

        $logs = (clone $query)->latest('id')->limit(20)->get();
        $lastDayQuery = NotificationLog::query()->where('created_at', '>=', now()->subDay());
        $sentLastDay = (clone $lastDayQuery)->where('status', NotificationLog::STATUS_SENT)->count();
        $failedLastDay = (clone $lastDayQuery)->where('status', NotificationLog::STATUS_FAILED)->count();
        $totalLastDay = max(1, $sentLastDay + $failedLastDay + (clone $lastDayQuery)->where('status', NotificationLog::STATUS_PENDING)->count());

        $channelBreakdown = (clone $query)
            ->selectRaw('channel, COUNT(*) as aggregate')
            ->groupBy('channel')
            ->orderByDesc('aggregate')
            ->get();

        return [
            'kpi' => [
                'label' => 'Notification failures',
                'value' => (clone $query)->where('status', NotificationLog::STATUS_FAILED)->count(),
                'delta' => $failedLastDay.' failed in last 24h',
                'status' => $failedLastDay > 0 ? 'warning' : 'healthy',
            ],
            'summary_24h' => [
                'sent' => $sentLastDay,
                'failed' => $failedLastDay,
                'success_rate' => number_format(($sentLastDay / $totalLastDay) * 100, 2, '.', ''),
            ],
            'channels' => $channelBreakdown->map(fn (object $row): array => [
                'channel' => $row->channel,
                'count' => (int) $row->aggregate,
                'status' => $this->channelStatus($row->channel),
            ])->all(),
            'events' => $logs->map(fn (NotificationLog $log): array => [
                'id' => $log->id,
                'recipient' => $log->user?->full_name ?: $log->user?->name ?: $log->user?->email ?: 'Unknown user',
                'channel' => $log->channel,
                'template_code' => $log->template_code,
                'status' => $log->status,
                'created_at' => $log->created_at?->toDateTimeString(),
                'failure_reason' => $log->response_payload['error'] ?? $log->response_payload['reason'] ?? null,
            ])->all(),
        ];
    }

    private function channelStatus(string $channel): string
    {
        return collect($this->notificationChannelManager->statuses())
            ->firstWhere('channel', $channel)['configured'] ?? false
            ? 'configured'
            : 'fallback';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function loyaltySection(array $filters): array
    {
        if (! Schema::hasTable('loyalty_history')) {
            return [
                'kpi' => [
                    'label' => 'Tier changes',
                    'value' => 0,
                    'delta' => '0 in last 24h',
                    'status' => 'idle',
                ],
                'summary_24h' => [
                    'tier_changes' => 0,
                    'upgrades' => 0,
                    'benefit_unlocks' => 0,
                ],
                'events' => [],
            ];
        }

        $query = LoyaltyHistory::query()
            ->with(['user:id,name,full_name,email', 'fromTier:id,name', 'toTier:id,name'])
            ->when($filters['date_from'], fn ($builder, $date) => $builder->whereDate('changed_at', '>=', $date))
            ->when($filters['date_to'], fn ($builder, $date) => $builder->whereDate('changed_at', '<=', $date));

        $events = (clone $query)->latest('changed_at')->limit(20)->get();
        $lastDayQuery = LoyaltyHistory::query()->where('changed_at', '>=', now()->subDay());
        $upgrades = (clone $lastDayQuery)->where('action', LoyaltyHistory::ACTION_UPGRADED)->count();

        return [
            'kpi' => [
                'label' => 'Tier changes',
                'value' => $events->count(),
                'delta' => $lastDayQuery->count().' in last 24h',
                'status' => $upgrades > 0 ? 'active' : 'idle',
            ],
            'summary_24h' => [
                'tier_changes' => $lastDayQuery->count(),
                'upgrades' => $upgrades,
                'benefit_unlocks' => NotificationLog::query()
                    ->where('template_code', 'LOYALTY_BENEFIT_UNLOCKED')
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
            ],
            'events' => $events->map(fn (LoyaltyHistory $entry): array => [
                'id' => $entry->id,
                'user' => $entry->user?->full_name ?: $entry->user?->name ?: $entry->user?->email ?: 'Unknown user',
                'action' => $entry->action,
                'from_tier' => $entry->fromTier?->name,
                'to_tier' => $entry->toTier?->name,
                'created_at' => $entry->changed_at?->toDateTimeString(),
            ])->all(),
        ];
    }
}