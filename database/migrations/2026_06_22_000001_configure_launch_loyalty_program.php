<?php

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configure the launch loyalty program: Member 2%, Silver 5%, Gold 8%, Platinum 12%.
     */
    public function up(): void
    {
        if (! Schema::hasTable('loyalty_tiers')) {
            return;
        }

        $legacyNewUserId = DB::table('loyalty_tiers')->where('level', 0)->value('id');

        DB::table('loyalty_tiers')->where('level', 0)->update([
            'is_active' => false,
            'is_default' => false,
            'updated_at' => now(),
        ]);

        $tierDefinitions = [
            1 => [
                'code' => 'member',
                'name' => 'Member',
                'badge_label' => 'Member',
                'color_token' => 'slate',
                'description' => 'Every registered customer starts here with a permanent 2% booking discount.',
                'sort_order' => 1,
                'is_default' => true,
            ],
            2 => [
                'code' => 'silver',
                'name' => 'Silver',
                'badge_label' => 'Silver',
                'color_token' => 'cyan',
                'description' => 'Unlocked after 5 completed bookings.',
                'sort_order' => 2,
                'is_default' => false,
            ],
            3 => [
                'code' => 'gold',
                'name' => 'Gold',
                'badge_label' => 'Gold',
                'color_token' => 'amber',
                'description' => 'Unlocked after 15 completed bookings.',
                'sort_order' => 3,
                'is_default' => false,
            ],
            4 => [
                'code' => 'platinum',
                'name' => 'Platinum',
                'badge_label' => 'Platinum',
                'color_token' => 'violet',
                'description' => 'Unlocked after 30 completed bookings.',
                'sort_order' => 4,
                'is_default' => false,
            ],
        ];

        foreach ($tierDefinitions as $level => $definition) {
            DB::table('loyalty_tiers')->where('level', $level)->update([
                ...$definition,
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }

        $tierIds = DB::table('loyalty_tiers')
            ->whereIn('level', array_keys($tierDefinitions))
            ->pluck('id', 'level');

        if ($legacyNewUserId !== null && $tierIds->has(1)) {
            DB::table('user_loyalty_profiles')
                ->where('current_tier_id', $legacyNewUserId)
                ->update(['current_tier_id' => $tierIds[1]]);
        }

        $ruleDefinitions = [
            1 => ['name' => 'Member on registration', 'min_completed_orders' => 0],
            2 => ['name' => 'Silver after 5 bookings', 'min_completed_orders' => 5],
            3 => ['name' => 'Gold after 15 bookings', 'min_completed_orders' => 15],
            4 => ['name' => 'Platinum after 30 bookings', 'min_completed_orders' => 30],
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
                    'min_completed_orders' => $definition['min_completed_orders'],
                    'min_lifetime_spend' => 0,
                    'min_period_orders' => 0,
                    'min_period_spend' => 0,
                    'period_days' => 365,
                    'allow_downgrade' => false,
                    'is_active' => true,
                    'priority' => $level,
                    'metadata' => json_encode(['launch_program' => true]),
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
                1 => ['code' => 'member_discount', 'name' => 'Member discount', 'value' => 2],
                2 => ['code' => 'silver_discount', 'name' => 'Silver discount', 'value' => 5],
                3 => ['code' => 'gold_discount', 'name' => 'Gold discount', 'value' => 8],
                4 => ['code' => 'platinum_discount', 'name' => 'Platinum discount', 'value' => 12],
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
                        'description' => 'Automatic loyalty discount applied at checkout.',
                        'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                        'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                        'value' => $definition['value'],
                        'configuration' => json_encode(['applies_to' => ['flight', 'hotel', 'insurance']]),
                        'display_order' => 1,
                        'is_highlighted' => true,
                        'is_active' => true,
                        'metadata' => json_encode(['launch_program' => true]),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Launch configuration is data-only; no structural rollback.
    }
};
