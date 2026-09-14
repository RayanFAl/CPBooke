<?php

namespace App\Modules\Admin\Loyalty\Services;

use App\Models\AuditLog;
use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyHistory;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyTier;
use App\Models\UserLoyaltyProfile;
use App\Modules\Audit\Services\AuditRecorder;
use App\Support\Rbac\RbacAuditLogger;
use App\Support\Rbac\RbacAuthorizer;
use Illuminate\Support\Facades\Schema;

class LoyaltyAdminService
{
    public function __construct(
        private readonly RbacAuthorizer $rbacAuthorizer,
        private readonly RbacAuditLogger $rbacAuditLogger,
        private readonly AuditRecorder $auditRecorder,
    ) {
    }

    /**
     * Build the loyalty admin dashboard payload.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $actor = $this->rbacAuthorizer->authorize('loyalty.view', allowSystem: true);
        $this->rbacAuditLogger->log('loyalty.dashboard.viewed', 'loyalty.view', $actor, 'loyalty_dashboard');

        if (! Schema::hasTable('loyalty_tiers')) {
            return [
                'metrics' => [
                    'profiles' => 0,
                    'upgrades_last_30_days' => 0,
                    'average_lifetime_spend' => '0.00',
                    'average_completed_orders' => 0,
                ],
                'tiers' => [],
                'rules' => [],
                'benefits' => [],
                'users_per_tier' => [],
                'recent_history' => [],
            ];
        }

        $tiers = LoyaltyTier::query()
            ->with([
                'rules' => fn ($query) => $query->orderByDesc('priority')->orderBy('id'),
                'benefits' => fn ($query) => $query->orderBy('display_order')->orderBy('id'),
                'profiles.user:id,name,full_name,email',
            ])
            ->orderBy('level')
            ->get();

        $profilesQuery = UserLoyaltyProfile::query();

        return [
            'metrics' => [
                'profiles' => (clone $profilesQuery)->count(),
                'upgrades_last_30_days' => LoyaltyHistory::query()
                    ->where('action', LoyaltyHistory::ACTION_UPGRADED)
                    ->where('changed_at', '>=', now()->subDays(30))
                    ->count(),
                'average_lifetime_spend' => number_format((float) (clone $profilesQuery)->avg('lifetime_spend'), 2, '.', ''),
                'average_completed_orders' => (int) round((float) (clone $profilesQuery)->avg('completed_orders_count')),
            ],
            'tiers' => $tiers->map(fn (LoyaltyTier $tier): array => [
                'id' => $tier->id,
                'level' => $tier->level,
                'code' => $tier->code,
                'name' => $tier->name,
                'description' => $tier->description,
                'badge_label' => $tier->badge_label,
                'color_token' => $tier->color_token,
                'sort_order' => $tier->sort_order,
                'is_active' => $tier->is_active,
                'is_default' => $tier->is_default,
                'users_count' => $tier->profiles->count(),
            ])->values()->all(),
            'rules' => $tiers->flatMap(fn (LoyaltyTier $tier) => $tier->rules->map(fn (LoyaltyRule $rule): array => [
                'id' => $rule->id,
                'tier_id' => $rule->tier_id,
                'tier_name' => $tier->name,
                'name' => $rule->name,
                'min_completed_orders' => $rule->min_completed_orders,
                'min_lifetime_spend' => number_format((float) $rule->min_lifetime_spend, 2, '.', ''),
                'min_period_orders' => $rule->min_period_orders,
                'min_period_spend' => number_format((float) $rule->min_period_spend, 2, '.', ''),
                'period_days' => $rule->period_days,
                'allow_downgrade' => $rule->allow_downgrade,
                'is_active' => $rule->is_active,
                'priority' => $rule->priority,
                'metadata' => $rule->metadata ?? [],
            ]))->values()->all(),
            'benefits' => $tiers->flatMap(fn (LoyaltyTier $tier) => $tier->benefits->map(fn (LoyaltyBenefit $benefit): array => [
                'id' => $benefit->id,
                'tier_id' => $benefit->tier_id,
                'tier_name' => $tier->name,
                'code' => $benefit->code,
                'name' => $benefit->name,
                'description' => $benefit->description,
                'benefit_type' => $benefit->benefit_type,
                'value_type' => $benefit->value_type,
                'value' => $benefit->value !== null ? number_format((float) $benefit->value, 2, '.', '') : '',
                'display_order' => $benefit->display_order,
                'is_highlighted' => $benefit->is_highlighted,
                'is_active' => $benefit->is_active,
            ]))->values()->all(),
            'users_per_tier' => $tiers->map(fn (LoyaltyTier $tier): array => [
                'tier' => [
                    'id' => $tier->id,
                    'level' => $tier->level,
                    'name' => $tier->name,
                    'code' => $tier->code,
                ],
                'users' => $tier->profiles->take(12)->map(fn (UserLoyaltyProfile $profile): array => [
                    'profile_id' => $profile->id,
                    'user' => [
                        'id' => $profile->user?->id,
                        'name' => $profile->user?->full_name ?: $profile->user?->name,
                        'email' => $profile->user?->email,
                    ],
                    'lifetime_spend' => number_format((float) $profile->lifetime_spend, 2, '.', ''),
                    'completed_orders_count' => $profile->completed_orders_count,
                    'progress_percentage' => $profile->progress_percentage,
                ])->values()->all(),
            ])->values()->all(),
            'recent_history' => LoyaltyHistory::query()
                ->with(['user:id,name,full_name,email', 'fromTier:id,name,level', 'toTier:id,name,level'])
                ->latest('changed_at')
                ->limit(12)
                ->get()
                ->map(fn (LoyaltyHistory $entry): array => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'user' => [
                        'id' => $entry->user?->id,
                        'name' => $entry->user?->full_name ?: $entry->user?->name,
                        'email' => $entry->user?->email,
                    ],
                    'from_tier' => $entry->fromTier?->name,
                    'to_tier' => $entry->toTier?->name,
                    'changed_at' => $entry->changed_at?->toDateTimeString(),
                    'notes' => $entry->notes,
                ])->values()->all(),
        ];
    }

    public function updateTier(LoyaltyTier $tier, array $data): LoyaltyTier
    {
        $actor = $this->rbacAuthorizer->authorize('loyalty.manage', allowSystem: true);
        $before = $this->tierSnapshot($tier);

        $tier->forceFill($data)->save();
        $tier = $tier->refresh();
        $after = $this->tierSnapshot($tier);
        [$oldValues, $newValues] = $this->diffSnapshots($before, $after);

        $this->rbacAuditLogger->log('loyalty.tier.updated', 'loyalty.manage', $actor, 'loyalty_tier', $tier->id, [
            'code' => $tier->code,
            'before' => $oldValues,
            'after' => $newValues,
        ]);

        if ($oldValues !== [] || $newValues !== []) {
            $this->auditRecorder->success(
                AuditLog::MODULE_LOYALTY,
                'loyalty.tier.updated',
                "Loyalty tier updated: {$tier->code} ({$tier->name})",
                AuditLog::ENTITY_LOYALTY_TIER,
                $tier->id,
                $actor,
                $oldValues,
                $newValues,
                ['source' => 'admin.loyalty', 'code' => $tier->code],
            );
        }

        return $tier;
    }

    public function updateRule(LoyaltyRule $rule, array $data): LoyaltyRule
    {
        $actor = $this->rbacAuthorizer->authorize('loyalty.manage-rules', allowSystem: true);
        $before = $this->ruleSnapshot($rule);

        $durationProvided = array_key_exists('benefit_duration_months', $data);
        $durationMonths = $durationProvided ? $data['benefit_duration_months'] : null;
        unset($data['benefit_duration_months']);

        if ($durationProvided) {
            $metadata = $rule->metadata ?? [];
            $metadata['benefit_duration_months'] = $durationMonths !== null && (int) $durationMonths > 0
                ? (int) $durationMonths
                : null;
            $data['metadata'] = $metadata;
        }

        $rule->forceFill($data)->save();
        $rule = $rule->refresh();
        $after = $this->ruleSnapshot($rule);
        [$oldValues, $newValues] = $this->diffSnapshots($before, $after);

        $this->rbacAuditLogger->log('loyalty.rule.updated', 'loyalty.manage-rules', $actor, 'loyalty_rule', $rule->id, [
            'tier_id' => $rule->tier_id,
            'name' => $rule->name,
            'benefit_duration_months' => $rule->metadata['benefit_duration_months'] ?? null,
            'before' => $oldValues,
            'after' => $newValues,
        ]);

        if ($oldValues !== [] || $newValues !== []) {
            $this->auditRecorder->success(
                AuditLog::MODULE_LOYALTY,
                'loyalty.rule.updated',
                "Loyalty rule updated: {$rule->name}",
                AuditLog::ENTITY_LOYALTY_RULE,
                $rule->id,
                $actor,
                $oldValues,
                $newValues,
                [
                    'source' => 'admin.loyalty',
                    'tier_id' => $rule->tier_id,
                ],
            );
        }

        return $rule;
    }

    public function updateBenefit(LoyaltyBenefit $benefit, array $data): LoyaltyBenefit
    {
        $actor = $this->rbacAuthorizer->authorize('loyalty.manage-benefits', allowSystem: true);
        $before = $this->benefitSnapshot($benefit);

        $benefit->forceFill($data)->save();
        $benefit = $benefit->refresh();
        $after = $this->benefitSnapshot($benefit);
        [$oldValues, $newValues] = $this->diffSnapshots($before, $after);

        $this->rbacAuditLogger->log('loyalty.benefit.updated', 'loyalty.manage-benefits', $actor, 'loyalty_benefit', $benefit->id, [
            'tier_id' => $benefit->tier_id,
            'code' => $benefit->code,
            'before' => $oldValues,
            'after' => $newValues,
        ]);

        if ($oldValues !== [] || $newValues !== []) {
            $this->auditRecorder->success(
                AuditLog::MODULE_LOYALTY,
                'loyalty.benefit.updated',
                "Loyalty benefit updated: {$benefit->code}",
                AuditLog::ENTITY_LOYALTY_BENEFIT,
                $benefit->id,
                $actor,
                $oldValues,
                $newValues,
                [
                    'source' => 'admin.loyalty',
                    'tier_id' => $benefit->tier_id,
                    'code' => $benefit->code,
                ],
            );
        }

        return $benefit;
    }

    /**
     * @return array<string, mixed>
     */
    private function tierSnapshot(LoyaltyTier $tier): array
    {
        return [
            'level' => $tier->level,
            'code' => $tier->code,
            'name' => $tier->name,
            'description' => $tier->description,
            'badge_label' => $tier->badge_label,
            'color_token' => $tier->color_token,
            'sort_order' => $tier->sort_order,
            'is_active' => (bool) $tier->is_active,
            'is_default' => (bool) $tier->is_default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleSnapshot(LoyaltyRule $rule): array
    {
        return [
            'tier_id' => $rule->tier_id,
            'rule_type' => $rule->rule_type,
            'name' => $rule->name,
            'min_completed_orders' => $rule->min_completed_orders,
            'min_lifetime_spend' => $rule->min_lifetime_spend !== null
                ? number_format((float) $rule->min_lifetime_spend, 2, '.', '')
                : null,
            'min_period_orders' => $rule->min_period_orders,
            'min_period_spend' => $rule->min_period_spend !== null
                ? number_format((float) $rule->min_period_spend, 2, '.', '')
                : null,
            'period_days' => $rule->period_days,
            'allow_downgrade' => (bool) $rule->allow_downgrade,
            'is_active' => (bool) $rule->is_active,
            'priority' => $rule->priority,
            'benefit_duration_months' => $rule->metadata['benefit_duration_months'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function benefitSnapshot(LoyaltyBenefit $benefit): array
    {
        return [
            'tier_id' => $benefit->tier_id,
            'code' => $benefit->code,
            'name' => $benefit->name,
            'description' => $benefit->description,
            'benefit_type' => $benefit->benefit_type,
            'value_type' => $benefit->value_type,
            'value' => $benefit->value !== null
                ? number_format((float) $benefit->value, 2, '.', '')
                : null,
            'display_order' => $benefit->display_order,
            'is_highlighted' => (bool) $benefit->is_highlighted,
            'is_active' => (bool) $benefit->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function diffSnapshots(array $before, array $after): array
    {
        $oldValues = [];
        $newValues = [];

        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;

            if ($this->valuesAreEquivalent($previous, $value)) {
                continue;
            }

            $oldValues[$key] = $previous;
            $newValues[$key] = $value;
        }

        return [$oldValues, $newValues];
    }

    private function valuesAreEquivalent(mixed $left, mixed $right): bool
    {
        if (is_array($left) || is_array($right)) {
            return json_encode($left) === json_encode($right);
        }

        if (is_bool($left) || is_bool($right)) {
            return (bool) $left === (bool) $right;
        }

        return (string) ($left ?? '') === (string) ($right ?? '');
    }
}
