<?php

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spend-based loyalty: 3 levels with timed discounts renewed on monthly spend thresholds.
     */
    public function up(): void
    {
        if (! Schema::hasTable('loyalty_tiers')) {
            return;
        }

        DB::table('loyalty_tiers')->update([
            'is_default' => false,
            'updated_at' => now(),
        ]);

        DB::table('loyalty_tiers')->whereIn('level', [0, 4])->update([
            'is_active' => false,
            'is_default' => false,
            'updated_at' => now(),
        ]);

        $tierDefinitions = [
            1 => [
                'code' => 'level_1',
                'name' => 'Level 1',
                'badge_label' => 'Level 1',
                'color_token' => 'cyan',
                'description' => 'Spend 1,000 in the current month to unlock 10% off for 5 months. Renew by spending 1,000 again in any month.',
                'sort_order' => 1,
            ],
            2 => [
                'code' => 'level_2',
                'name' => 'Level 2',
                'badge_label' => 'Level 2',
                'color_token' => 'amber',
                'description' => 'Spend 10,000 in the current month to unlock 25% off for 9 months.',
                'sort_order' => 2,
            ],
            3 => [
                'code' => 'level_3',
                'name' => 'Level 3',
                'badge_label' => 'Level 3',
                'color_token' => 'violet',
                'description' => 'Spend 25,000 in the current month to unlock 45% off for 12 months.',
                'sort_order' => 3,
            ],
        ];

        foreach ($tierDefinitions as $level => $definition) {
            DB::table('loyalty_tiers')->where('level', $level)->update([
                ...$definition,
                'is_active' => true,
                'is_default' => false,
                'updated_at' => now(),
            ]);
        }

        $tierIds = DB::table('loyalty_tiers')
            ->whereIn('level', array_keys($tierDefinitions))
            ->pluck('id', 'level');

        $ruleDefinitions = [
            1 => [
                'name' => 'Level 1 monthly spend target',
                'min_period_spend' => 1000,
                'benefit_duration_months' => 5,
            ],
            2 => [
                'name' => 'Level 2 monthly spend target',
                'min_period_spend' => 10000,
                'benefit_duration_months' => 9,
            ],
            3 => [
                'name' => 'Level 3 monthly spend target',
                'min_period_spend' => 25000,
                'benefit_duration_months' => 12,
            ],
        ];

        foreach ($ruleDefinitions as $level => $definition) {
            if (! $tierIds->has($level)) {
                continue;
            }

            DB::table('loyalty_rules')->updateOrInsert(
                [
                    'tier_id' => $tierIds[$level],
                    'rule_type' => LoyaltyRule::TYPE_UPGRADE,
                ],
                [
                    'name' => $definition['name'],
                    'min_completed_orders' => 0,
                    'min_lifetime_spend' => 0,
                    'min_period_orders' => 0,
                    'min_period_spend' => $definition['min_period_spend'],
                    'period_days' => 30,
                    'allow_downgrade' => true,
                    'is_active' => true,
                    'priority' => $level,
                    'metadata' => json_encode([
                        'evaluation_mode' => 'spend_duration',
                        'qualification_window' => 'calendar_month',
                        'benefit_duration_months' => $definition['benefit_duration_months'],
                    ]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        if (Schema::hasTable('loyalty_benefits')) {
            DB::table('loyalty_benefits')->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

            $benefitDefinitions = [
                1 => ['code' => 'level_1_discount', 'name' => 'Level 1 discount', 'value' => 10],
                2 => ['code' => 'level_2_discount', 'name' => 'Level 2 discount', 'value' => 25],
                3 => ['code' => 'level_3_discount', 'name' => 'Level 3 discount', 'value' => 45],
            ];

            foreach ($benefitDefinitions as $level => $definition) {
                if (! $tierIds->has($level)) {
                    continue;
                }

                DB::table('loyalty_benefits')->updateOrInsert(
                    [
                        'tier_id' => $tierIds[$level],
                        'code' => $definition['code'],
                    ],
                    [
                        'name' => $definition['name'],
                        'description' => 'Timed loyalty discount applied while the level entitlement is active.',
                        'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                        'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                        'value' => $definition['value'],
                        'configuration' => json_encode(['applies_to' => ['flight', 'hotel', 'insurance']]),
                        'display_order' => 1,
                        'is_highlighted' => true,
                        'is_active' => true,
                        'metadata' => json_encode(['evaluation_mode' => 'spend_duration']),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }

        if (Schema::hasTable('user_loyalty_profiles')) {
            DB::table('user_loyalty_profiles')->update([
                'current_tier_id' => null,
                'next_tier_id' => null,
                'progress_percentage' => 0,
                'metadata' => json_encode(['entitlements' => []]),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data-only migration.
    }
};
