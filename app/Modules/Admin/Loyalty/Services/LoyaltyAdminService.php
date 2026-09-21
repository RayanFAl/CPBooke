<?php

namespace App\Modules\Admin\Loyalty\Services;

use App\Models\AuditLog;
use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyCompanyRate;
use App\Models\LoyaltyHistory;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyTier;
use App\Models\Order;
use App\Models\UserLoyaltyProfile;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Loyalty\Events\LoyaltyDiscountCampaignQueued;
use App\Support\Rbac\RbacAuditLogger;
use App\Support\Rbac\RbacAuthorizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoyaltyAdminService
{
    public function __construct(
        private readonly RbacAuthorizer $rbacAuthorizer,
        private readonly RbacAuditLogger $rbacAuditLogger,
        private readonly AuditRecorder $auditRecorder,
    ) {}

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

    /**
     * Create a new loyalty level with rule, discount benefit, and default company rates.
     *
     * @param  array{
     *     name?: string|null,
     *     discount_percentage: float|int|string,
     *     monthly_spend?: float|int|string|null,
     *     duration_months?: int|string|null,
     *     duration_days?: int|string|null,
     *     duration_unit?: string|null,
     *     is_active?: bool,
     *     notify_customers?: bool
     * }  $data
     */
    public function createTier(array $data): LoyaltyTier
    {
        $actor = $this->rbacAuthorizer->authorize('loyalty.manage', allowSystem: true);
        $discount = max(0, min(100, round((float) $data['discount_percentage'], 2)));

        $tier = DB::transaction(function () use ($data, $discount): LoyaltyTier {
            $nextLevel = (int) (LoyaltyTier::query()->max('level') ?? 0) + 1;

            if ($nextLevel < 1) {
                $nextLevel = 1;
            }

            $name = trim((string) ($data['name'] ?? '')) ?: "Level {$nextLevel}";
            $code = 'level_'.$nextLevel;
            $suffix = 2;

            while (LoyaltyTier::query()->where('code', $code)->exists()) {
                $code = 'level_'.$nextLevel.'_'.$suffix;
                $suffix++;
            }

            $monthlySpend = max(0, round((float) ($data['monthly_spend'] ?? 0), 2));
            $durationMeta = $this->normalizeDurationMetadata(
                $data['duration_unit'] ?? null,
                $data['duration_days'] ?? null,
                $data['duration_months'] ?? null,
            );

            $tier = LoyaltyTier::query()->create([
                'level' => $nextLevel,
                'code' => $code,
                'name' => $name,
                'badge_label' => $name,
                'description' => $monthlySpend > 0
                    ? "Unlocked after {$monthlySpend} monthly spend."
                    : 'Starter loyalty level.',
                'color_token' => 'slate',
                'sort_order' => $nextLevel,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
                'is_default' => false,
                'metadata' => [
                    'name_en' => $name,
                    'name_ar' => $name,
                    'mobile_code' => $code,
                    'mobile_benefits' => [],
                ],
            ]);

            LoyaltyRule::query()->create([
                'tier_id' => $tier->id,
                'rule_type' => LoyaltyRule::TYPE_UPGRADE,
                'name' => $monthlySpend > 0
                    ? "{$name} monthly spend target"
                    : "{$name} on registration",
                'min_completed_orders' => 0,
                'min_lifetime_spend' => 0,
                'min_period_orders' => 0,
                'min_period_spend' => $monthlySpend,
                'period_days' => 30,
                'allow_downgrade' => false,
                'is_active' => true,
                'priority' => max(1, 100 - $nextLevel),
                'metadata' => $durationMeta,
            ]);

            LoyaltyBenefit::query()->create([
                'tier_id' => $tier->id,
                'code' => $code.'_discount',
                'name' => "{$name} discount",
                'description' => "{$discount}% loyalty discount",
                'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                'value' => $discount,
                'configuration' => ['applies_to' => Order::serviceTypes()],
                'applies_to_services' => Order::serviceTypes(),
                'priority' => 10,
                'stackable' => false,
                'finance_sensitive' => false,
                'display_order' => 1,
                'is_highlighted' => true,
                'is_active' => true,
                'metadata' => [
                    'label_en' => rtrim(rtrim(number_format($discount, 2, '.', ''), '0'), '.').'% off bookings',
                    'label_ar' => 'خصم '.rtrim(rtrim(number_format($discount, 2, '.', ''), '0'), '.').'% على الحجوزات',
                ],
            ]);

            $this->seedCompanyRatesForTier($tier, $discount);

            return $tier->refresh();
        });

        $this->rbacAuditLogger->log('loyalty.tier.created', 'loyalty.manage', $actor, 'loyalty_tier', $tier->id, [
            'code' => $tier->code,
            'level' => $tier->level,
        ]);

        $this->auditRecorder->success(
            AuditLog::MODULE_LOYALTY,
            'loyalty.tier.created',
            "Loyalty tier created: {$tier->code} ({$tier->name})",
            AuditLog::ENTITY_LOYALTY_TIER,
            $tier->id,
            $actor,
            null,
            $this->tierSnapshot($tier),
            ['source' => 'admin.loyalty', 'code' => $tier->code],
        );

        if ($this->shouldNotifyCustomers($data, $tier)) {
            $durationLabels = $this->campaignDurationLabels($data);

            event(new LoyaltyDiscountCampaignQueued(
                tierId: (int) $tier->id,
                tierName: (string) $tier->name,
                discountPercentage: rtrim(rtrim(number_format($discount, 2, '.', ''), '0'), '.'),
                durationLabelEn: $durationLabels['en'],
                durationLabelAr: $durationLabels['ar'],
            ));
        }

        return $tier;
    }

    /**
     * Copy an existing level (rule, benefits, company rates) into a new level.
     * Admin then only needs to change the values they want.
     */
    public function duplicateTier(LoyaltyTier $source): LoyaltyTier
    {
        $actor = $this->rbacAuthorizer->authorize('loyalty.manage', allowSystem: true);

        $source->loadMissing(['rules', 'benefits']);

        $tier = DB::transaction(function () use ($source): LoyaltyTier {
            $nextLevel = (int) (LoyaltyTier::query()->max('level') ?? 0) + 1;

            if ($nextLevel < 1) {
                $nextLevel = 1;
            }

            $baseName = trim((string) $source->name) ?: "Level {$nextLevel}";
            $name = $baseName.' (copy)';
            $code = 'level_'.$nextLevel;
            $suffix = 2;

            while (LoyaltyTier::query()->where('code', $code)->exists()) {
                $code = 'level_'.$nextLevel.'_'.$suffix;
                $suffix++;
            }

            $sourceMeta = is_array($source->metadata) ? $source->metadata : [];
            $metadata = array_merge($sourceMeta, [
                'name_en' => trim((string) ($sourceMeta['name_en'] ?? $baseName)).' (copy)',
                'name_ar' => trim((string) ($sourceMeta['name_ar'] ?? $baseName)).' (نسخة)',
                'mobile_code' => $code,
                'duplicated_from_tier_id' => $source->id,
            ]);

            $tier = LoyaltyTier::query()->create([
                'level' => $nextLevel,
                'code' => $code,
                'name' => $name,
                'badge_label' => $name,
                'description' => $source->description,
                'color_token' => $source->color_token ?: 'slate',
                'sort_order' => $nextLevel,
                'is_active' => (bool) $source->is_active,
                'is_default' => false,
                'metadata' => $metadata,
            ]);

            foreach ($source->rules as $rule) {
                LoyaltyRule::query()->create([
                    'tier_id' => $tier->id,
                    'rule_type' => $rule->rule_type,
                    'name' => str_replace($baseName, $name, (string) $rule->name),
                    'min_completed_orders' => $rule->min_completed_orders,
                    'min_lifetime_spend' => $rule->min_lifetime_spend,
                    'min_period_orders' => $rule->min_period_orders,
                    'min_period_spend' => $rule->min_period_spend,
                    'period_days' => $rule->period_days,
                    'allow_downgrade' => (bool) $rule->allow_downgrade,
                    'is_active' => (bool) $rule->is_active,
                    'priority' => max(1, 100 - $nextLevel),
                    'metadata' => $rule->metadata ?? [],
                ]);
            }

            foreach ($source->benefits as $benefit) {
                $benefitCode = $code.'_'.preg_replace('/^'.preg_quote((string) $source->code, '/').'_?/', '', (string) $benefit->code);
                $benefitCode = trim((string) $benefitCode, '_');

                if ($benefitCode === '' || $benefitCode === $code) {
                    $benefitCode = $code.'_benefit_'.$benefit->id;
                }

                while (LoyaltyBenefit::query()->where('code', $benefitCode)->exists()) {
                    $benefitCode .= '_copy';
                }

                LoyaltyBenefit::query()->create([
                    'tier_id' => $tier->id,
                    'code' => $benefitCode,
                    'name' => str_replace($baseName, $name, (string) $benefit->name),
                    'description' => $benefit->description,
                    'benefit_type' => $benefit->benefit_type,
                    'value_type' => $benefit->value_type,
                    'value' => $benefit->value,
                    'configuration' => $benefit->configuration ?? [],
                    'applies_to_services' => $benefit->applies_to_services ?? [],
                    'minimum_order_amount' => $benefit->minimum_order_amount,
                    'maximum_discount_amount' => $benefit->maximum_discount_amount,
                    'priority' => $benefit->priority,
                    'stackable' => (bool) $benefit->stackable,
                    'finance_sensitive' => (bool) $benefit->finance_sensitive,
                    'display_order' => $benefit->display_order,
                    'is_highlighted' => (bool) $benefit->is_highlighted,
                    'is_active' => (bool) $benefit->is_active,
                    'metadata' => $benefit->metadata ?? [],
                ]);
            }

            if (Schema::hasTable('loyalty_company_rates')) {
                $now = now();
                $rateRows = LoyaltyCompanyRate::query()
                    ->where('tier_id', $source->id)
                    ->get()
                    ->map(static fn (LoyaltyCompanyRate $rate): array => [
                        'tier_id' => $tier->id,
                        'service_type' => $rate->service_type,
                        'company_key' => $rate->company_key,
                        'company_name' => $rate->company_name,
                        'discount_percentage' => $rate->discount_percentage,
                        'is_active' => (bool) $rate->is_active,
                        'sort_order' => $rate->sort_order,
                        'metadata' => json_encode(array_merge(
                            is_array($rate->metadata) ? $rate->metadata : [],
                            ['duplicated_from_tier_id' => $source->id],
                        )),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                if ($rateRows !== []) {
                    LoyaltyCompanyRate::query()->insertOrIgnore($rateRows);
                }
            }

            return $tier->refresh();
        });

        $this->rbacAuditLogger->log('loyalty.tier.duplicated', 'loyalty.manage', $actor, 'loyalty_tier', $tier->id, [
            'code' => $tier->code,
            'level' => $tier->level,
            'source_tier_id' => $source->id,
            'source_code' => $source->code,
        ]);

        $this->auditRecorder->success(
            AuditLog::MODULE_LOYALTY,
            'loyalty.tier.duplicated',
            "Loyalty tier duplicated from {$source->code} to {$tier->code}",
            AuditLog::ENTITY_LOYALTY_TIER,
            $tier->id,
            $actor,
            null,
            $this->tierSnapshot($tier),
            [
                'source' => 'admin.loyalty',
                'code' => $tier->code,
                'source_tier_id' => $source->id,
            ],
        );

        return $tier;
    }

    private function seedCompanyRatesForTier(LoyaltyTier $tier, float $discountPercentage): void
    {
        if (! Schema::hasTable('loyalty_company_rates')) {
            return;
        }

        $now = now();
        $formatted = number_format($discountPercentage, 2, '.', '');
        $rows = [];

        foreach (LoyaltyCompanyRate::catalogCompanies() as $company) {
            $rows[] = [
                'tier_id' => $tier->id,
                'service_type' => $company['service_type'],
                'company_key' => $company['company_key'],
                'company_name' => $company['company_name'],
                'discount_percentage' => $formatted,
                'is_active' => true,
                'sort_order' => $company['sort_order'],
                'metadata' => json_encode(['seeded_on_create' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $airlines = LoyaltyCompanyRate::query()
            ->where('service_type', Order::SERVICE_TYPE_FLIGHT)
            ->select('company_key', 'company_name', 'sort_order')
            ->distinct()
            ->orderBy('sort_order')
            ->get();

        foreach ($airlines as $index => $airline) {
            $rows[] = [
                'tier_id' => $tier->id,
                'service_type' => Order::SERVICE_TYPE_FLIGHT,
                'company_key' => $airline->company_key,
                'company_name' => $airline->company_name ?: $airline->company_key,
                'discount_percentage' => $formatted,
                'is_active' => true,
                'sort_order' => (int) ($airline->sort_order ?? $index),
                'metadata' => json_encode(['seeded_on_create' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            LoyaltyCompanyRate::query()->insertOrIgnore($rows);
        }
    }

    public function updateRule(LoyaltyRule $rule, array $data): LoyaltyRule
    {
        $actor = $this->rbacAuthorizer->authorize('loyalty.manage-rules', allowSystem: true);
        $before = $this->ruleSnapshot($rule);

        $durationProvided = array_key_exists('benefit_duration_months', $data)
            || array_key_exists('benefit_duration_days', $data)
            || array_key_exists('benefit_duration_unit', $data)
            || array_key_exists('duration_months', $data)
            || array_key_exists('duration_days', $data)
            || array_key_exists('duration_unit', $data);

        $durationMeta = null;

        if ($durationProvided) {
            $durationMeta = $this->normalizeDurationMetadata(
                $data['benefit_duration_unit'] ?? $data['duration_unit'] ?? null,
                $data['benefit_duration_days'] ?? $data['duration_days'] ?? null,
                $data['benefit_duration_months'] ?? $data['duration_months'] ?? null,
            );
        }

        unset(
            $data['benefit_duration_months'],
            $data['benefit_duration_days'],
            $data['benefit_duration_unit'],
            $data['duration_months'],
            $data['duration_days'],
            $data['duration_unit'],
        );

        if ($durationMeta !== null) {
            $data['metadata'] = array_merge($rule->metadata ?? [], $durationMeta);
        }

        $rule->forceFill($data)->save();
        $rule = $rule->refresh();
        $after = $this->ruleSnapshot($rule);
        [$oldValues, $newValues] = $this->diffSnapshots($before, $after);

        $this->rbacAuditLogger->log('loyalty.rule.updated', 'loyalty.manage-rules', $actor, 'loyalty_rule', $rule->id, [
            'tier_id' => $rule->tier_id,
            'name' => $rule->name,
            'benefit_duration_months' => $rule->metadata['benefit_duration_months'] ?? null,
            'benefit_duration_days' => $rule->metadata['benefit_duration_days'] ?? null,
            'benefit_duration_unit' => $rule->metadata['benefit_duration_unit'] ?? null,
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
            'benefit_duration_days' => $rule->metadata['benefit_duration_days'] ?? null,
            'benefit_duration_unit' => $rule->metadata['benefit_duration_unit'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function shouldNotifyCustomers(array $data, LoyaltyTier $tier): bool
    {
        if (! $tier->is_active) {
            return false;
        }

        if (array_key_exists('notify_customers', $data)) {
            return (bool) $data['notify_customers'];
        }

        $unit = (string) ($data['duration_unit'] ?? '');
        $days = isset($data['duration_days']) && $data['duration_days'] !== ''
            ? (int) $data['duration_days']
            : 0;

        return $unit === 'days' && $days > 0;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{en: string, ar: string}
     */
    private function campaignDurationLabels(array $data): array
    {
        $unit = (string) ($data['duration_unit'] ?? '');
        $days = isset($data['duration_days']) && $data['duration_days'] !== ''
            ? (int) $data['duration_days']
            : 0;
        $months = isset($data['duration_months']) && $data['duration_months'] !== ''
            ? (int) $data['duration_months']
            : 0;

        if ($unit === 'days' && $days > 0) {
            return [
                'en' => $days === 1 ? '1 day' : "{$days} days",
                'ar' => $days === 1 ? 'يوم واحد' : "{$days} أيام",
            ];
        }

        if (($unit === 'months' || $unit === '') && $months > 0) {
            return [
                'en' => $months === 1 ? '1 month' : "{$months} months",
                'ar' => $months === 1 ? 'شهر واحد' : "{$months} أشهر",
            ];
        }

        return [
            'en' => 'a limited time',
            'ar' => 'فترة محدودة',
        ];
    }

    /**
     * @return array{
     *     benefit_duration_unit: string|null,
     *     benefit_duration_days: int|null,
     *     benefit_duration_months: int|null
     * }
     */
    private function normalizeDurationMetadata(mixed $unit, mixed $days, mixed $months): array
    {
        $normalizedUnit = is_string($unit) && in_array($unit, ['days', 'months'], true)
            ? $unit
            : null;
        $daysValue = $days !== null && $days !== '' ? (int) $days : 0;
        $monthsValue = $months !== null && $months !== '' ? (int) $months : 0;

        if ($normalizedUnit === null) {
            if ($daysValue > 0 && $monthsValue <= 0) {
                $normalizedUnit = 'days';
            } elseif ($monthsValue > 0) {
                $normalizedUnit = 'months';
            }
        }

        if ($normalizedUnit === 'days' && $daysValue > 0) {
            return [
                'benefit_duration_unit' => 'days',
                'benefit_duration_days' => $daysValue,
                'benefit_duration_months' => null,
            ];
        }

        if ($normalizedUnit === 'months' && $monthsValue > 0) {
            return [
                'benefit_duration_unit' => 'months',
                'benefit_duration_days' => null,
                'benefit_duration_months' => $monthsValue,
            ];
        }

        // Legacy: duration_months alone without unit.
        if ($monthsValue > 0) {
            return [
                'benefit_duration_unit' => 'months',
                'benefit_duration_days' => null,
                'benefit_duration_months' => $monthsValue,
            ];
        }

        return [
            'benefit_duration_unit' => null,
            'benefit_duration_days' => null,
            'benefit_duration_months' => null,
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
