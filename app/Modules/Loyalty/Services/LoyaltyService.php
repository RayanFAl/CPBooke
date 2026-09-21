<?php

namespace App\Modules\Loyalty\Services;

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyHistory;
use App\Models\LoyaltyRule;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Models\Order;
use App\Models\User;
use App\Models\UserLoyaltyProfile;
use App\Modules\Loyalty\Pricing\LoyaltyDiscountableFareResolver;
use App\Modules\Loyalty\Support\LoyaltyResultsPromo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoyaltyService
{
    private ?bool $loyaltySchemaAvailable = null;

    public function __construct(
        private readonly LoyaltyCompanyRateResolver $companyRateResolver,
    ) {}

    /**
     * Determine the user's current tier from the dynamic rule set.
     */
    public function calculateUserTier(User $user): ?LoyaltyTier
    {
        if (! $this->loyaltySchemaIsAvailable()) {
            return null;
        }

        $evaluation = $this->evaluate($user);

        return $evaluation['eligible_tier'];
    }

    /**
     * Recalculate the user's loyalty state and persist profile/history updates.
     */
    public function upgradeUserIfEligible(User $user, ?object $triggerEvent = null): UserLoyaltyProfile
    {
        return DB::transaction(function () use ($user, $triggerEvent): UserLoyaltyProfile {
            $evaluation = $this->evaluate($user);

            /** @var UserLoyaltyProfile $profile */
            $profile = UserLoyaltyProfile::query()->where('user_id', $user->id)->first()
                ?? new UserLoyaltyProfile([
                    'user_id' => $user->id,
                ]);

            $fromTier = $profile->currentTier;
            $toTier = $evaluation['effective_tier'];

            $welcomeConsumedAt = $profile->metadata['welcome_consumed_at'] ?? null;

            if ($welcomeConsumedAt === null && (int) ($evaluation['metrics']['completed_orders_count'] ?? 0) >= 1) {
                $welcomeConsumedAt = now()->toIso8601String();
            }

            $profile->forceFill([
                'current_tier_id' => $toTier?->id,
                'next_tier_id' => $evaluation['next_tier']?->id,
                'lifetime_orders_count' => $evaluation['metrics']['lifetime_orders_count'],
                'completed_orders_count' => $evaluation['metrics']['completed_orders_count'],
                'lifetime_spend' => $evaluation['metrics']['lifetime_spend'],
                'period_orders_count' => $evaluation['metrics']['period_orders_count'],
                'period_spend' => $evaluation['metrics']['month_spend'] ?? $evaluation['metrics']['period_spend'],
                'progress_percentage' => $evaluation['progress_percentage'],
                'last_calculated_at' => now(),
                'upgraded_at' => $this->timestampForAction($profile, $fromTier, $toTier, LoyaltyHistory::ACTION_UPGRADED),
                'downgraded_at' => $this->timestampForAction($profile, $fromTier, $toTier, LoyaltyHistory::ACTION_DOWNGRADED),
                'metadata' => array_merge($profile->metadata ?? [], array_filter([
                    'entitlements' => $evaluation['entitlements'] ?? ($profile->metadata['entitlements'] ?? []),
                    'evaluation_mode' => ($evaluation['entitlements'] ?? null) !== null ? 'spend_duration' : ($profile->metadata['evaluation_mode'] ?? null),
                    'welcome_consumed_at' => $welcomeConsumedAt,
                ], static fn (mixed $value): bool => $value !== null)),
            ])->save();

            if ($fromTier?->id !== $toTier?->id) {
                LoyaltyHistory::query()->create([
                    'user_id' => $user->id,
                    'from_tier_id' => $fromTier?->id,
                    'to_tier_id' => $toTier?->id,
                    'order_id' => $this->resolveOrderIdFromEvent($triggerEvent),
                    'action' => $this->resolveHistoryAction($fromTier, $toTier),
                    'trigger_event_class' => $triggerEvent ? $triggerEvent::class : null,
                    'metrics_snapshot' => $evaluation['metrics'],
                    'rule_snapshot' => $this->ruleSnapshot($evaluation['applied_rule']),
                    'notes' => $this->resolveHistoryNote($fromTier, $toTier),
                    'changed_at' => now(),
                ]);
            }

            return $profile->refresh()->loadMissing([
                'currentTier.benefits',
                'nextTier',
            ]);
        });
    }

    /**
     * Build a benefits application preview for an order without coupling to checkout state.
     *
     * @return array<string, mixed>
     */
    public function applyBenefitsToOrder(Order $order): array
    {
        $user = $order->customer()->first();

        if (! $user instanceof User) {
            return [
                'tier' => null,
                'benefits' => [],
                'pricing' => [
                    'base_total' => number_format((float) $order->total_amount, 2, '.', ''),
                    'discount_amount' => '0.00',
                    'final_total' => number_format((float) $order->total_amount, 2, '.', ''),
                ],
                'service_flags' => [],
            ];
        }

        $profile = $user->loyaltyProfile()->with('currentTier.benefits')->first();

        if ($profile === null || $profile->currentTier === null) {
            return [
                'tier' => null,
                'benefits' => [],
                'pricing' => [
                    'base_total' => number_format((float) $order->total_amount, 2, '.', ''),
                    'discount_amount' => '0.00',
                    'final_total' => number_format((float) $order->total_amount, 2, '.', ''),
                ],
                'service_flags' => [],
            ];
        }

        // Welcome / Level 1 discount ends after the first completed order.
        if (
            filled($profile->metadata['welcome_consumed_at'] ?? null)
            || (int) $profile->completed_orders_count >= 1
        ) {
            $entitlement = $profile->metadata['entitlements'][(string) $profile->current_tier_id] ?? null;
            $isWelcomeTier = (bool) $profile->currentTier->is_default
                || (
                    is_array($entitlement)
                    && (
                        ($entitlement['grant_reason'] ?? null) === 'welcome'
                        || (bool) ($entitlement['ends_after_first_order'] ?? false)
                    )
                );

            if ($isWelcomeTier) {
                $totalAmount = (float) $order->total_amount;
                $taxAmount = $order->tax_amount !== null ? (float) $order->tax_amount : null;
                $fareAmount = LoyaltyDiscountableFareResolver::resolve(
                    $totalAmount,
                    $taxAmount,
                    $order->base_amount !== null ? (float) $order->base_amount : null,
                );

                return [
                    'tier' => null,
                    'benefits' => [],
                    'pricing' => [
                        'base_total' => number_format($totalAmount, 2, '.', ''),
                        'fare_amount' => number_format($fareAmount, 2, '.', ''),
                        'tax_amount' => $taxAmount !== null ? number_format($taxAmount, 2, '.', '') : null,
                        'discount_amount' => '0.00',
                        'final_total' => number_format(LoyaltyDiscountableFareResolver::finalTotal($fareAmount, 0.0, $taxAmount), 2, '.', ''),
                    ],
                    'service_flags' => [],
                ];
            }
        }

        $benefits = $profile->currentTier->benefits->where('is_active', true)->values();

        $totalAmount = (float) $order->total_amount;
        $taxAmount = $order->tax_amount !== null ? (float) $order->tax_amount : null;
        $fareAmount = LoyaltyDiscountableFareResolver::resolve(
            $totalAmount,
            $taxAmount,
            $order->base_amount !== null ? (float) $order->base_amount : null,
        );
        $discountAmount = $this->calculateDiscountAmount(
            $fareAmount,
            $benefits,
            $profile->currentTier,
            (string) $order->service_type,
            is_array($order->details) ? $order->details : [],
        );
        $finalTotal = LoyaltyDiscountableFareResolver::finalTotal($fareAmount, $discountAmount, $taxAmount);
        $serviceFlags = $this->serviceFlags($benefits);

        return [
            'tier' => $this->tierPayload($profile->currentTier),
            'benefits' => $benefits->map(fn (LoyaltyBenefit $benefit): array => $this->benefitPayload($benefit))->values()->all(),
            'pricing' => [
                'base_total' => number_format($totalAmount, 2, '.', ''),
                'fare_amount' => number_format($fareAmount, 2, '.', ''),
                'tax_amount' => $taxAmount !== null ? number_format($taxAmount, 2, '.', '') : null,
                'discount_amount' => number_format($discountAmount, 2, '.', ''),
                'final_total' => number_format($finalTotal, 2, '.', ''),
            ],
            'service_flags' => $serviceFlags,
        ];
    }

    /**
     * Return the currently unlocked benefits for the user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserBenefits(User $user): array
    {
        if (! $this->loyaltySchemaIsAvailable()) {
            return [];
        }

        $profile = $user->loyaltyProfile()->with('currentTier.benefits')->first();

        if ($profile === null || $profile->currentTier === null) {
            return [];
        }

        return $profile->currentTier->benefits
            ->where('is_active', true)
            ->map(fn (LoyaltyBenefit $benefit): array => $this->benefitPayload($benefit))
            ->values()
            ->all();
    }

    /**
     * Build the frontend-friendly profile snapshot for customer surfaces.
     *
     * @return array<string, mixed>
     */
    public function profilePayload(User $user, bool $initializeIfMissing = true): array
    {
        if (! $this->loyaltySchemaIsAvailable()) {
            return $this->emptyProfilePayload();
        }

        $profile = $user->loyaltyProfile()
            ->with([
                'currentTier.benefits',
                'nextTier.rules',
                'nextTier.benefits',
            ])
            ->first();

        if ($profile === null && $initializeIfMissing) {
            $profile = $this->upgradeUserIfEligible($user);
            $profile->load([
                'currentTier.benefits',
                'nextTier.rules',
                'nextTier.benefits',
            ]);
        }

        $history = $user->loyaltyHistory()
            ->with(['fromTier:id,name,level', 'toTier:id,name,level'])
            ->latest('changed_at')
            ->limit(10)
            ->get();

        $programTiers = $this->programTiersCatalog();
        $monthSpend = round((float) ($profile?->period_spend ?? 0), 2);

        return [
            'program' => $this->programSettingsPayload(),
            'tiers' => $programTiers,
            'current_level' => $profile?->currentTier?->level ?? 0,
            'current_tier' => $profile?->currentTier ? $this->tierMobilePayload($profile->currentTier) : null,
            'next_tier' => $profile?->nextTier ? $this->tierMobilePayload($profile->nextTier) : null,
            'membership' => $this->membershipPayload($profile),
            'welcome_active' => $this->isWelcomeActive($profile),
            'show_welcome_message' => $this->shouldShowWelcomeMessage($profile),
            'welcome_message' => $this->welcomeMessage($profile),
            'progress_to_next_level' => $this->progressToNextLevelPayload($profile),
            'monthly_spend' => $monthSpend,
            'total_savings' => $this->totalSavingsForProfile($profile),
            'entitlement' => $this->entitlementPayload($profile),
            'benefits_unlocked' => $profile !== null ? $this->getUserBenefits($user) : [],
            'history' => $history->map(fn (LoyaltyHistory $entry): array => [
                'id' => $entry->id,
                'action' => $entry->action,
                'from_tier' => $entry->fromTier ? $this->tierPayload($entry->fromTier) : null,
                'to_tier' => $entry->toTier ? $this->tierPayload($entry->toTier) : null,
                'changed_at' => $entry->changed_at?->toIso8601String(),
                'notes' => $entry->notes,
            ])->values()->all(),
            'last_calculated_at' => $profile?->last_calculated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyProfilePayload(): array
    {
        return [
            'program' => [
                'enabled' => false,
                'visible_in_mobile_app' => false,
                'default_currency' => 'LYD',
                'welcome_ends_after_first_order' => true,
                'rules' => [
                    'welcome_tier_code' => 'welcome',
                    'welcome_ends_after_first_completed_order' => true,
                    'higher_tiers_unlock_by_monthly_spend' => true,
                ],
                'results_promo' => LoyaltyResultsPromo::defaults(),
            ],
            'tiers' => [],
            'current_level' => 0,
            'current_tier' => null,
            'next_tier' => null,
            'membership' => null,
            'welcome_active' => false,
            'show_welcome_message' => false,
            'welcome_message' => null,
            'progress_to_next_level' => $this->progressToNextLevelPayload(null),
            'monthly_spend' => 0.0,
            'total_savings' => 0.0,
            'entitlement' => null,
            'benefits_unlocked' => [],
            'history' => [],
            'last_calculated_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function programSettingsPayload(): array
    {
        $settings = LoyaltySetting::current();
        $metadata = is_array($settings->metadata) ? $settings->metadata : [];

        return [
            'enabled' => (bool) $settings->loyalty_enabled,
            'visible_in_mobile_app' => (bool) $settings->visible_in_mobile_app,
            'default_currency' => (string) ($settings->default_currency ?: 'LYD'),
            // Booke+: Welcome (Level 1) is stage one and ends on the first completed order.
            'welcome_ends_after_first_order' => true,
            'rules' => [
                'welcome_tier_code' => 'welcome',
                'welcome_ends_after_first_completed_order' => true,
                'higher_tiers_unlock_by_monthly_spend' => true,
            ],
            'results_promo' => LoyaltyResultsPromo::resolve(
                is_array($metadata['results_promo'] ?? null) ? $metadata['results_promo'] : null,
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function programTiersCatalog(): array
    {
        $tiers = LoyaltyTier::query()
            ->where('is_active', true)
            ->where('level', '>', 0)
            ->with([
                'rules' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where('rule_type', LoyaltyRule::TYPE_UPGRADE),
                'benefits' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('level')
            ->get();

        return $tiers
            ->map(fn (LoyaltyTier $tier): array => $this->tierMobilePayload($tier))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function progressToNextLevelPayload(?UserLoyaltyProfile $profile): array
    {
        $monthSpend = round((float) ($profile?->period_spend ?? 0), 2);

        return [
            'percentage' => (int) ($profile?->progress_percentage ?? 0),
            'current_metrics' => [
                'lifetime_orders_count' => (int) ($profile?->lifetime_orders_count ?? 0),
                'completed_orders_count' => (int) ($profile?->completed_orders_count ?? 0),
                'lifetime_spend' => number_format((float) ($profile?->lifetime_spend ?? 0), 2, '.', ''),
                'month_spend' => $monthSpend,
                'period_orders_count' => (int) ($profile?->period_orders_count ?? 0),
                'period_spend' => number_format((float) ($profile?->period_spend ?? 0), 2, '.', ''),
            ],
            'next_threshold' => $this->nextThresholdAmount($profile),
            'amount_remaining' => $this->amountRemaining($profile),
        ];
    }

    private function amountRemaining(?UserLoyaltyProfile $profile): ?string
    {
        $threshold = $this->nextThresholdAmount($profile);

        if ($threshold === null) {
            return null;
        }

        $monthSpend = (float) ($profile?->period_spend ?? 0);
        $remaining = max(((float) $threshold) - $monthSpend, 0);

        return number_format($remaining, 2, '.', '');
    }

    private function loyaltySchemaIsAvailable(): bool
    {
        if ($this->loyaltySchemaAvailable !== null) {
            return $this->loyaltySchemaAvailable;
        }

        $requiredTables = [
            (new LoyaltyTier)->getTable(),
            (new LoyaltyRule)->getTable(),
            (new LoyaltyBenefit)->getTable(),
            (new UserLoyaltyProfile)->getTable(),
            (new LoyaltyHistory)->getTable(),
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                return $this->loyaltySchemaAvailable = false;
            }
        }

        return $this->loyaltySchemaAvailable = true;
    }

    /**
     * @return array{eligible_tier: ?LoyaltyTier, effective_tier: ?LoyaltyTier, next_tier: ?LoyaltyTier, applied_rule: ?LoyaltyRule, metrics: array<string, int|float>, progress_percentage: int}
     */
    private function evaluate(User $user): array
    {
        $tiers = LoyaltyTier::query()
            ->where('is_active', true)
            ->with([
                'rules' => fn ($query) => $query->where('is_active', true)->where('rule_type', LoyaltyRule::TYPE_UPGRADE),
                'benefits' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('level')
            ->get();

        $orders = $user->orders()->with('transactions')->get();
        $metrics = $this->aggregateMetrics($orders, 30);
        $metrics['month_spend'] = $this->calendarMonthSpend($orders);

        if ($this->usesSpendDurationProgram($tiers)) {
            return $this->evaluateSpendDurationProgram($user, $tiers, $orders, $metrics);
        }

        $defaultTier = $tiers->firstWhere('is_default', true) ?: $tiers->sortBy('level')->first();
        $rules = $tiers->flatMap(fn (LoyaltyTier $tier): Collection => $tier->rules)->values();
        $maxPeriodDays = max((int) $rules->max('period_days'), 365);

        $eligibleRule = $rules
            ->sortByDesc(fn (LoyaltyRule $rule): int => $rule->tier?->level ?? 0)
            ->first(fn (LoyaltyRule $rule): bool => $this->ruleMatches($rule, $orders, $metrics));

        $eligibleTier = $eligibleRule?->tier ?: $defaultTier;
        $currentProfile = $user->loyaltyProfile()->with('currentTier.rules')->first();
        $effectiveTier = $eligibleTier;

        if ($currentProfile?->currentTier !== null && $eligibleTier !== null && $eligibleTier->level < $currentProfile->currentTier->level) {
            $currentRule = $currentProfile->currentTier->rules
                ->firstWhere('rule_type', LoyaltyRule::TYPE_UPGRADE);

            if (! ($currentRule?->allow_downgrade ?? false)) {
                $effectiveTier = $currentProfile->currentTier;
            }
        }

        $nextTier = $tiers
            ->sortBy('level')
            ->first(fn (LoyaltyTier $tier): bool => $effectiveTier !== null && $tier->level > $effectiveTier->level);

        return [
            'eligible_tier' => $eligibleTier,
            'effective_tier' => $effectiveTier,
            'next_tier' => $nextTier,
            'applied_rule' => $eligibleRule,
            'metrics' => $metrics,
            'progress_percentage' => $this->progressPercentage($nextTier, $orders, $metrics),
            'entitlements' => null,
        ];
    }

    /**
     * @param  Collection<int, LoyaltyTier>  $tiers
     * @param  Collection<int, Order>  $orders
     * @param  array<string, int|float>  $metrics
     * @return array{eligible_tier: ?LoyaltyTier, effective_tier: ?LoyaltyTier, next_tier: ?LoyaltyTier, applied_rule: ?LoyaltyRule, metrics: array<string, int|float>, progress_percentage: int, entitlements: array<string, array<string, mixed>>}
     */
    private function evaluateSpendDurationProgram(User $user, Collection $tiers, Collection $orders, array $metrics): array
    {
        $profile = $user->loyaltyProfile()->first();
        $entitlements = $profile?->metadata['entitlements'] ?? [];
        $monthSpend = (float) $metrics['month_spend'];
        $now = now();

        foreach ($tiers as $tier) {
            $rule = $tier->rules->firstWhere('rule_type', LoyaltyRule::TYPE_UPGRADE);

            if (! $rule instanceof LoyaltyRule) {
                continue;
            }

            $threshold = (float) $rule->min_period_spend;
            $durationMonths = (int) ($rule->metadata['benefit_duration_months'] ?? 0);
            $durationDays = (int) ($rule->metadata['benefit_duration_days'] ?? 0);
            $durationUnit = (string) ($rule->metadata['benefit_duration_unit'] ?? '');
            $isStarterTier = (bool) $tier->is_default || $threshold <= 0;

            // Welcome / Level 1: active from registration until the first completed order.
            if ($isStarterTier) {
                $welcomeConsumed = filled($profile?->metadata['welcome_consumed_at'] ?? null)
                    || (int) $metrics['completed_orders_count'] >= 1;

                if ($welcomeConsumed) {
                    unset($entitlements[(string) $tier->id]);

                    continue;
                }

                $existing = $entitlements[(string) $tier->id] ?? [];
                $entitlements[(string) $tier->id] = [
                    'tier_id' => $tier->id,
                    'tier_code' => $tier->code,
                    'tier_level' => $tier->level,
                    'qualified_at' => $existing['qualified_at'] ?? $now->toIso8601String(),
                    'expires_at' => null,
                    'qualification_spend' => 0,
                    'threshold' => 0,
                    'duration_months' => null,
                    'duration_days' => null,
                    'duration_unit' => null,
                    'grant_reason' => 'welcome',
                    'welcome_active' => true,
                    'ends_after_first_order' => true,
                ];

                continue;
            }

            $resolvedDuration = $this->resolveBenefitDuration(
                $durationUnit,
                $durationDays,
                $durationMonths,
            );

            if ($resolvedDuration === null || $monthSpend < $threshold) {
                continue;
            }

            $existing = $entitlements[(string) $tier->id] ?? [];
            $existingExpires = isset($existing['expires_at']) && is_string($existing['expires_at'])
                ? Carbon::parse($existing['expires_at'])
                : null;
            $stillActive = $existingExpires !== null && $existingExpires->greaterThan($now);

            $entitlements[(string) $tier->id] = [
                'tier_id' => $tier->id,
                'tier_code' => $tier->code,
                'tier_level' => $tier->level,
                'qualified_at' => $stillActive
                    ? ($existing['qualified_at'] ?? $now->toIso8601String())
                    : $now->toIso8601String(),
                'expires_at' => $stillActive
                    ? $existingExpires->toIso8601String()
                    : $resolvedDuration['expires_at']->toIso8601String(),
                'qualification_spend' => $monthSpend,
                'threshold' => $threshold,
                'duration_months' => $resolvedDuration['unit'] === 'months' ? $resolvedDuration['value'] : null,
                'duration_days' => $resolvedDuration['unit'] === 'days' ? $resolvedDuration['value'] : null,
                'duration_unit' => $resolvedDuration['unit'],
            ];
        }

        $activeEntitlements = collect($entitlements)
            ->filter(function (array $entitlement) use ($now): bool {
                $expiresAt = $entitlement['expires_at'] ?? null;

                if ($expiresAt === null) {
                    return true;
                }

                return is_string($expiresAt) && Carbon::parse($expiresAt)->greaterThan($now);
            });

        $entitlements = $activeEntitlements->all();

        $activeTierIds = $activeEntitlements
            ->pluck('tier_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        $effectiveTier = $tiers
            ->whereIn('id', $activeTierIds)
            ->sortByDesc('level')
            ->first();

        $appliedRule = $effectiveTier?->rules->firstWhere('rule_type', LoyaltyRule::TYPE_UPGRADE);

        $nextTier = $tiers
            ->sortBy('level')
            ->first(function (LoyaltyTier $tier) use ($effectiveTier, $activeTierIds): bool {
                $rule = $tier->rules->firstWhere('rule_type', LoyaltyRule::TYPE_UPGRADE);
                $isStarterTier = (bool) $tier->is_default
                    || (float) ($rule?->min_period_spend ?? 1) <= 0;

                // After welcome ends, progress points at the first earnable (non-welcome) level.
                if ($effectiveTier === null) {
                    return ! $isStarterTier;
                }

                if ($tier->level <= $effectiveTier->level) {
                    return false;
                }

                return ! in_array($tier->id, $activeTierIds, true);
            });

        return [
            'eligible_tier' => $effectiveTier,
            'effective_tier' => $effectiveTier,
            'next_tier' => $nextTier,
            'applied_rule' => $appliedRule,
            'metrics' => $metrics,
            'progress_percentage' => $this->spendDurationProgressPercentage($nextTier, $monthSpend),
            'entitlements' => $entitlements,
        ];
    }

    /**
     * @param  Collection<int, LoyaltyTier>  $tiers
     */
    private function usesSpendDurationProgram(Collection $tiers): bool
    {
        return $tiers->contains(function (LoyaltyTier $tier): bool {
            $rule = $tier->rules->firstWhere('rule_type', LoyaltyRule::TYPE_UPGRADE);

            return ($rule?->metadata['evaluation_mode'] ?? null) === 'spend_duration';
        });
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function calendarMonthSpend(Collection $orders): float
    {
        $monthStart = now()->startOfMonth();

        $qualifiedOrders = $orders->filter(fn (Order $order): bool => in_array($order->status, [
            Order::STATUS_CONFIRMED,
            Order::STATUS_COMPLETED,
            Order::STATUS_REFUNDED,
        ], true));

        return round($qualifiedOrders
            ->filter(fn (Order $order): bool => $order->created_at !== null && $order->created_at->greaterThanOrEqualTo($monthStart))
            ->sum(fn (Order $order): float => (float) $order->getNetPaidAmount()), 2);
    }

    private function spendDurationProgressPercentage(?LoyaltyTier $nextTier, float $monthSpend): int
    {
        if ($nextTier === null) {
            return 100;
        }

        $rule = $nextTier->rules->firstWhere('rule_type', LoyaltyRule::TYPE_UPGRADE);
        $threshold = (float) ($rule?->min_period_spend ?? 0);

        if ($threshold <= 0) {
            return 100;
        }

        return (int) round(min($monthSpend / $threshold, 1) * 100);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<string, int|float>
     */
    private function aggregateMetrics(Collection $orders, int $defaultPeriodDays): array
    {
        $qualifiedOrders = $orders->filter(fn (Order $order): bool => in_array($order->status, [
            Order::STATUS_CONFIRMED,
            Order::STATUS_COMPLETED,
            Order::STATUS_REFUNDED,
        ], true));

        $periodStart = now()->subDays(max($defaultPeriodDays, 1));
        $periodOrders = $qualifiedOrders->filter(fn (Order $order): bool => $order->created_at !== null && $order->created_at->greaterThanOrEqualTo($periodStart));

        return [
            'lifetime_orders_count' => $orders->count(),
            'completed_orders_count' => $qualifiedOrders->count(),
            'lifetime_spend' => round($qualifiedOrders->sum(fn (Order $order): float => (float) $order->getNetPaidAmount()), 2),
            'period_orders_count' => $periodOrders->count(),
            'period_spend' => round($periodOrders->sum(fn (Order $order): float => (float) $order->getNetPaidAmount()), 2),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  array<string, int|float>  $metrics
     */
    private function ruleMatches(LoyaltyRule $rule, Collection $orders, array $metrics): bool
    {
        $periodMetrics = $this->periodMetricsForRule($orders, $rule);

        return (int) $metrics['completed_orders_count'] >= $rule->min_completed_orders
            && (float) $metrics['lifetime_spend'] >= (float) $rule->min_lifetime_spend
            && $periodMetrics['period_orders_count'] >= $rule->min_period_orders
            && $periodMetrics['period_spend'] >= (float) $rule->min_period_spend;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{period_orders_count: int, period_spend: float}
     */
    private function periodMetricsForRule(Collection $orders, LoyaltyRule $rule): array
    {
        $qualifiedOrders = $orders->filter(fn (Order $order): bool => in_array($order->status, [
            Order::STATUS_CONFIRMED,
            Order::STATUS_COMPLETED,
            Order::STATUS_REFUNDED,
        ], true));

        $startDate = Carbon::now()->subDays(max($rule->period_days, 1));
        $periodOrders = $qualifiedOrders->filter(fn (Order $order): bool => $order->created_at !== null && $order->created_at->greaterThanOrEqualTo($startDate));

        return [
            'period_orders_count' => $periodOrders->count(),
            'period_spend' => round($periodOrders->sum(fn (Order $order): float => (float) $order->getNetPaidAmount()), 2),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  array<string, int|float>  $metrics
     */
    private function progressPercentage(?LoyaltyTier $nextTier, Collection $orders, array $metrics): int
    {
        if ($nextTier === null) {
            return 100;
        }

        $rule = $nextTier->rules->firstWhere('rule_type', LoyaltyRule::TYPE_UPGRADE);

        if (! $rule instanceof LoyaltyRule) {
            return 100;
        }

        $periodMetrics = $this->periodMetricsForRule($orders, $rule);
        $ratios = [];

        if ($rule->min_completed_orders > 0) {
            $ratios[] = min(((int) $metrics['completed_orders_count']) / $rule->min_completed_orders, 1);
        }

        if ((float) $rule->min_lifetime_spend > 0) {
            $ratios[] = min(((float) $metrics['lifetime_spend']) / (float) $rule->min_lifetime_spend, 1);
        }

        if ($rule->min_period_orders > 0) {
            $ratios[] = min($periodMetrics['period_orders_count'] / $rule->min_period_orders, 1);
        }

        if ((float) $rule->min_period_spend > 0) {
            $ratios[] = min($periodMetrics['period_spend'] / (float) $rule->min_period_spend, 1);
        }

        if ($ratios === []) {
            return 100;
        }

        return (int) round((array_sum($ratios) / count($ratios)) * 100);
    }

    /**
     * @param  Collection<int, LoyaltyBenefit>  $benefits
     * @param  array<string, mixed>  $attributes
     */
    private function calculateDiscountAmount(
        float $fareAmount,
        Collection $benefits,
        LoyaltyTier $tier,
        string $serviceType,
        array $attributes = [],
    ): float {
        return round($benefits
            ->filter(fn (LoyaltyBenefit $benefit): bool => $benefit->benefit_type === LoyaltyBenefit::TYPE_DISCOUNT)
            ->sum(function (LoyaltyBenefit $benefit) use ($fareAmount, $tier, $serviceType, $attributes): float {
                if ($benefit->value_type === LoyaltyBenefit::VALUE_TYPE_PERCENTAGE) {
                    $percentage = $this->companyRateResolver->resolvePercentage(
                        $tier,
                        $serviceType,
                        $attributes,
                        $benefit,
                    );

                    if ($percentage === null) {
                        return 0.0;
                    }

                    return $fareAmount * ($percentage / 100);
                }

                if ($benefit->value_type === LoyaltyBenefit::VALUE_TYPE_FIXED) {
                    return min((float) $benefit->value, $fareAmount);
                }

                return 0.0;
            }), 2);
    }

    /**
     * @param  Collection<int, LoyaltyBenefit>  $benefits
     * @return array<int, string>
     */
    private function serviceFlags(Collection $benefits): array
    {
        return $benefits
            ->filter(fn (LoyaltyBenefit $benefit): bool => in_array($benefit->benefit_type, [
                LoyaltyBenefit::TYPE_SUPPORT,
                LoyaltyBenefit::TYPE_SERVICE,
                LoyaltyBenefit::TYPE_UPGRADE,
                LoyaltyBenefit::TYPE_OFFER,
            ], true))
            ->map(fn (LoyaltyBenefit $benefit): string => $benefit->code)
            ->values()
            ->all();
    }

    private function resolveHistoryAction(?LoyaltyTier $fromTier, ?LoyaltyTier $toTier): string
    {
        if (($toTier?->level ?? 0) >= ($fromTier?->level ?? 0)) {
            return LoyaltyHistory::ACTION_UPGRADED;
        }

        return LoyaltyHistory::ACTION_DOWNGRADED;
    }

    private function resolveHistoryNote(?LoyaltyTier $fromTier, ?LoyaltyTier $toTier): string
    {
        if ($fromTier === null && $toTier !== null) {
            return 'Loyalty profile initialized.';
        }

        if (($toTier?->level ?? 0) > ($fromTier?->level ?? 0)) {
            return 'User was automatically upgraded by the loyalty rules engine.';
        }

        return 'User tier changed after loyalty rules re-evaluation.';
    }

    private function resolveOrderIdFromEvent(?object $event): ?int
    {
        if ($event === null) {
            return null;
        }

        return Arr::get($event, 'order.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleSnapshot(?LoyaltyRule $rule): array
    {
        if ($rule === null) {
            return [];
        }

        return [
            'id' => $rule->id,
            'tier_id' => $rule->tier_id,
            'name' => $rule->name,
            'min_completed_orders' => $rule->min_completed_orders,
            'min_lifetime_spend' => (float) $rule->min_lifetime_spend,
            'min_period_orders' => $rule->min_period_orders,
            'min_period_spend' => (float) $rule->min_period_spend,
            'period_days' => $rule->period_days,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tierPayload(LoyaltyTier $tier): array
    {
        return [
            'id' => $tier->id,
            'level' => $tier->level,
            'code' => $tier->code,
            'name' => $tier->name,
            'description' => $tier->description,
            'badge_label' => $tier->badge_label,
            'color_token' => $tier->color_token,
        ];
    }

    /**
     * Tier snapshot for mobile clients (Booke+), including discount, localization, and benefits.
     *
     * @return array<string, mixed>
     */
    private function tierMobilePayload(LoyaltyTier $tier): array
    {
        $rule = $tier->relationLoaded('rules')
            ? $tier->rules->firstWhere('rule_type', LoyaltyRule::TYPE_UPGRADE)
            : $tier->rules()
                ->where('is_active', true)
                ->where('rule_type', LoyaltyRule::TYPE_UPGRADE)
                ->first();

        $monthlySpendRequired = $rule instanceof LoyaltyRule
            ? round((float) $rule->min_period_spend, 2)
            : 0.0;

        $durationMonths = null;
        $durationDays = null;
        $durationUnit = null;

        if ($rule instanceof LoyaltyRule) {
            $resolved = $this->resolveBenefitDuration(
                (string) ($rule->metadata['benefit_duration_unit'] ?? ''),
                (int) ($rule->metadata['benefit_duration_days'] ?? 0),
                (int) ($rule->metadata['benefit_duration_months'] ?? 0),
            );

            if ($resolved !== null) {
                $durationUnit = $resolved['unit'];
                $durationMonths = $resolved['unit'] === 'months' ? $resolved['value'] : null;
                $durationDays = $resolved['unit'] === 'days' ? $resolved['value'] : null;
            }
        }

        $discountPercentage = $this->primaryDiscountPercentageForTier($tier);
        $mobileCode = $this->mobileTierCode($tier);
        [$nameEn, $nameAr] = $this->tierLocalizedNames($tier, $mobileCode);

        return [
            'id' => $tier->id,
            'level' => $tier->level,
            'code' => $mobileCode,
            'source_code' => $tier->code,
            'name' => $nameEn,
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'description' => $tier->description,
            'badge_label' => $tier->badge_label,
            'color_token' => $tier->color_token,
            'discount_percentage' => $discountPercentage,
            'monthly_spend_required' => $monthlySpendRequired,
            'active_for_months' => $durationMonths,
            'active_for_days' => $durationDays,
            'duration_unit' => $durationUnit,
            'ends_after_first_order' => $mobileCode === 'welcome' || (bool) $tier->is_default,
            'benefits' => $this->tierMobileBenefits($tier, $discountPercentage),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function tierLocalizedNames(LoyaltyTier $tier, string $mobileCode): array
    {
        $defaults = match ($mobileCode) {
            'welcome' => ['Welcome', 'مرحباً'],
            'explorer' => ['Explorer', 'مستكشف'],
            'gold' => ['Gold', 'ذهبي'],
            'platinum' => ['Platinum', 'بلاتيني'],
            default => [$tier->name, $tier->name],
        };

        $nameEn = trim((string) ($tier->metadata['name_en'] ?? '')) ?: $defaults[0];
        $nameAr = trim((string) ($tier->metadata['name_ar'] ?? '')) ?: $defaults[1];

        return [$nameEn, $nameAr];
    }

    private function mobileTierCode(LoyaltyTier $tier): string
    {
        $fromMeta = trim((string) ($tier->metadata['mobile_code'] ?? ''));

        if ($fromMeta !== '') {
            return $fromMeta;
        }

        return match ($tier->code) {
            'level_1', 'welcome' => 'welcome',
            'level_2', 'explorer' => 'explorer',
            'level_3', 'gold' => 'gold',
            'vip', 'platinum', 'elite' => 'platinum',
            default => $tier->code,
        };
    }

    /**
     * @return array<int, array{label_ar: string, label_en: string, code?: string, benefit_type?: string}>
     */
    private function tierMobileBenefits(LoyaltyTier $tier, ?float $discountPercentage): array
    {
        $benefits = $tier->relationLoaded('benefits')
            ? $tier->benefits->where('is_active', true)->values()
            : $tier->benefits()->where('is_active', true)->orderBy('display_order')->orderBy('id')->get();

        $items = [];

        foreach ($benefits as $benefit) {
            if (! $benefit instanceof LoyaltyBenefit) {
                continue;
            }

            $labelEn = trim((string) ($benefit->metadata['label_en'] ?? ''));
            $labelAr = trim((string) ($benefit->metadata['label_ar'] ?? ''));

            if ($labelEn === '' || $labelAr === '') {
                if (
                    $benefit->benefit_type === LoyaltyBenefit::TYPE_DISCOUNT
                    && $benefit->value_type === LoyaltyBenefit::VALUE_TYPE_PERCENTAGE
                    && $benefit->value !== null
                ) {
                    $formatted = rtrim(rtrim(number_format((float) $benefit->value, 2, '.', ''), '0'), '.');
                    $labelEn = $labelEn !== '' ? $labelEn : sprintf('%s%% off bookings', $formatted);
                    $labelAr = $labelAr !== '' ? $labelAr : sprintf('خصم %s%% على الحجوزات', $formatted);
                } else {
                    $labelEn = $labelEn !== '' ? $labelEn : (string) $benefit->name;
                    $labelAr = $labelAr !== '' ? $labelAr : (string) ($benefit->description ?: $benefit->name);
                }
            }

            $items[] = [
                'code' => $benefit->code,
                'label_ar' => $labelAr,
                'label_en' => $labelEn,
                'benefit_type' => $benefit->benefit_type,
            ];
        }

        if ($items === [] && $discountPercentage !== null) {
            $formatted = rtrim(rtrim(number_format($discountPercentage, 2, '.', ''), '0'), '.');
            $items[] = [
                'label_ar' => sprintf('خصم %s%% على الحجوزات', $formatted),
                'label_en' => sprintf('%s%% off bookings', $formatted),
            ];
        }

        $extra = $tier->metadata['mobile_benefits'] ?? null;

        if (is_array($extra)) {
            foreach ($extra as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $labelEn = trim((string) ($row['label_en'] ?? ''));
                $labelAr = trim((string) ($row['label_ar'] ?? ''));

                if ($labelEn === '' && $labelAr === '') {
                    continue;
                }

                $items[] = [
                    'label_ar' => $labelAr !== '' ? $labelAr : $labelEn,
                    'label_en' => $labelEn !== '' ? $labelEn : $labelAr,
                ];
            }
        }

        return array_values($items);
    }

    private function totalSavingsForProfile(?UserLoyaltyProfile $profile): float
    {
        if ($profile === null) {
            return 0.0;
        }

        $fromMeta = $profile->metadata['total_savings'] ?? null;

        if (is_numeric($fromMeta)) {
            return round((float) $fromMeta, 2);
        }

        return 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function benefitPayload(LoyaltyBenefit $benefit): array
    {
        return [
            'id' => $benefit->id,
            'code' => $benefit->code,
            'name' => $benefit->name,
            'description' => $benefit->description,
            'benefit_type' => $benefit->benefit_type,
            'value_type' => $benefit->value_type,
            'value' => $benefit->value !== null ? number_format((float) $benefit->value, 2, '.', '') : null,
            'configuration' => $benefit->configuration ?? [],
            'is_highlighted' => (bool) $benefit->is_highlighted,
            'label_ar' => trim((string) ($benefit->metadata['label_ar'] ?? '')) ?: null,
            'label_en' => trim((string) ($benefit->metadata['label_en'] ?? '')) ?: null,
        ];
    }

    private function timestampForAction(UserLoyaltyProfile $profile, ?LoyaltyTier $fromTier, ?LoyaltyTier $toTier, string $action): ?Carbon
    {
        if ($fromTier?->id === $toTier?->id) {
            return $action === LoyaltyHistory::ACTION_UPGRADED
                ? $profile->upgraded_at
                : $profile->downgraded_at;
        }

        $resolvedAction = $this->resolveHistoryAction($fromTier, $toTier);

        if ($resolvedAction !== $action) {
            return $action === LoyaltyHistory::ACTION_UPGRADED
                ? $profile->upgraded_at
                : $profile->downgraded_at;
        }

        return now();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function membershipPayload(?UserLoyaltyProfile $profile): ?array
    {
        if ($profile === null || $profile->currentTier === null) {
            return null;
        }

        $discountPercentage = $this->primaryDiscountPercentage($profile);

        if ($discountPercentage === null) {
            return null;
        }

        $mobileCode = $this->mobileTierCode($profile->currentTier);
        [$nameEn] = $this->tierLocalizedNames($profile->currentTier, $mobileCode);

        return [
            'discount_percentage' => $discountPercentage,
            'checkout_label' => sprintf('Booke+ %s', $nameEn),
            'expires_at' => $this->activeEntitlementForProfile($profile)['expires_at'] ?? null,
            'welcome_active' => $this->isWelcomeActive($profile),
            'ends_after_first_order' => $this->isWelcomeActive($profile),
        ];
    }

    private function shouldShowWelcomeMessage(?UserLoyaltyProfile $profile): bool
    {
        return $this->isWelcomeActive($profile);
    }

    /**
     * @return array<string, string>|null
     */
    private function welcomeMessage(?UserLoyaltyProfile $profile): ?array
    {
        if (! $this->shouldShowWelcomeMessage($profile)) {
            return null;
        }

        $percentage = $this->primaryDiscountPercentage($profile);
        $formatted = $percentage !== null
            ? rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.')
            : '3';

        return [
            'en' => sprintf('Welcome! You have a %s%% Booke+ discount until your first completed order.', $formatted),
            'ar' => sprintf('مرحباً بك! لديك خصم Booke+ بنسبة %s%% حتى أول طلب مكتمل.', $formatted),
        ];
    }

    public function isWelcomeActive(?UserLoyaltyProfile $profile): bool
    {
        if ($profile === null || $profile->currentTier === null) {
            return false;
        }

        if (filled($profile->metadata['welcome_consumed_at'] ?? null)) {
            return false;
        }

        if ((int) $profile->completed_orders_count >= 1) {
            return false;
        }

        $entitlement = $this->activeEntitlementForProfile($profile);

        if (! is_array($entitlement)) {
            return false;
        }

        return ($entitlement['grant_reason'] ?? null) === 'welcome'
            || (bool) ($entitlement['welcome_active'] ?? false)
            || (bool) ($entitlement['ends_after_first_order'] ?? false)
            || (bool) $profile->currentTier->is_default;
    }

    private function primaryDiscountPercentage(?UserLoyaltyProfile $profile): ?float
    {
        if ($profile === null || $profile->currentTier === null) {
            return null;
        }

        return $this->primaryDiscountPercentageForTier($profile->currentTier);
    }

    private function primaryDiscountPercentageForTier(LoyaltyTier $tier): ?float
    {
        $benefits = $tier->relationLoaded('benefits')
            ? $tier->benefits
            : $tier->benefits()->where('is_active', true)->get();

        $benefit = $benefits
            ->where('is_active', true)
            ->where('benefit_type', LoyaltyBenefit::TYPE_DISCOUNT)
            ->where('value_type', LoyaltyBenefit::VALUE_TYPE_PERCENTAGE)
            ->sortByDesc('is_highlighted')
            ->sortBy('display_order')
            ->first();

        if ($benefit === null || $benefit->value === null) {
            return null;
        }

        return (float) $benefit->value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function entitlementPayload(?UserLoyaltyProfile $profile): ?array
    {
        $active = $this->activeEntitlementForProfile($profile);

        if ($active === null) {
            return null;
        }

        $expiresAtRaw = $active['expires_at'] ?? null;
        $expiresAt = is_string($expiresAtRaw) ? Carbon::parse($expiresAtRaw) : null;

        return [
            'tier_code' => $active['tier_code'] ?? null,
            'qualified_at' => $active['qualified_at'] ?? null,
            'expires_at' => $expiresAt?->toIso8601String(),
            'days_remaining' => $expiresAt !== null
                ? max(0, (int) now()->diffInDays($expiresAt, false))
                : null,
            'duration_months' => isset($active['duration_months'])
                ? (int) $active['duration_months']
                : null,
            'duration_days' => isset($active['duration_days'])
                ? (int) $active['duration_days']
                : null,
            'duration_unit' => $active['duration_unit'] ?? (
                isset($active['duration_days']) && (int) $active['duration_days'] > 0
                    ? 'days'
                    : (isset($active['duration_months']) && (int) $active['duration_months'] > 0 ? 'months' : null)
            ),
            'qualification_spend' => isset($active['qualification_spend'])
                ? number_format((float) $active['qualification_spend'], 2, '.', '')
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeEntitlementForProfile(?UserLoyaltyProfile $profile): ?array
    {
        if ($profile === null || $profile->currentTier === null) {
            return null;
        }

        $entitlements = $profile->metadata['entitlements'] ?? [];
        $tierId = (string) $profile->current_tier_id;
        $entitlement = $entitlements[$tierId] ?? null;

        if (! is_array($entitlement)) {
            return null;
        }

        $expiresAt = $entitlement['expires_at'] ?? null;

        if ($expiresAt === null) {
            return $entitlement;
        }

        if (! is_string($expiresAt) || ! Carbon::parse($expiresAt)->greaterThan(now())) {
            return null;
        }

        return $entitlement;
    }

    private function nextThresholdAmount(?UserLoyaltyProfile $profile): ?string
    {
        if ($profile?->nextTier === null) {
            return null;
        }

        $rule = $profile->nextTier->rules()
            ->where('is_active', true)
            ->where('rule_type', LoyaltyRule::TYPE_UPGRADE)
            ->first();

        if ($rule === null || (float) $rule->min_period_spend <= 0) {
            return null;
        }

        return number_format((float) $rule->min_period_spend, 2, '.', '');
    }

    /**
     * @return array{unit: string, value: int, expires_at: \Carbon\Carbon}|null
     */
    private function resolveBenefitDuration(string $unit, int $days, int $months): ?array
    {
        $normalizedUnit = in_array($unit, ['days', 'months'], true)
            ? $unit
            : ($days > 0 && $months <= 0 ? 'days' : 'months');

        if ($normalizedUnit === 'days') {
            if ($days <= 0) {
                return null;
            }

            return [
                'unit' => 'days',
                'value' => $days,
                'expires_at' => now()->copy()->addDays($days),
            ];
        }

        if ($months <= 0) {
            return null;
        }

        return [
            'unit' => 'months',
            'value' => $months,
            'expires_at' => now()->copy()->addMonths($months),
        ];
    }
}
