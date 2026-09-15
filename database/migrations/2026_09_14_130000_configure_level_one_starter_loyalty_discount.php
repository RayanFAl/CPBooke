<?php

use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase one: Level 1 is a permanent 3% starter discount on register/login.
     * Higher tiers remain spend-duration unlocks.
     */
    public function up(): void
    {
        if (! Schema::hasTable('loyalty_tiers')) {
            return;
        }

        $levelOneId = DB::table('loyalty_tiers')->where('code', 'level_1')->value('id')
            ?? DB::table('loyalty_tiers')->where('level', 1)->value('id');

        if ($levelOneId === null) {
            return;
        }

        DB::table('loyalty_tiers')->where('id', '!=', $levelOneId)->update([
            'is_default' => false,
            'updated_at' => now(),
        ]);

        DB::table('loyalty_tiers')->where('id', $levelOneId)->update([
            'name' => 'Level 1',
            'badge_label' => 'Level 1',
            'description' => 'Permanent 3% booking discount granted on registration or first login.',
            'is_active' => true,
            'is_default' => true,
            'updated_at' => now(),
        ]);

        DB::table('loyalty_rules')->updateOrInsert(
            [
                'tier_id' => $levelOneId,
                'rule_type' => LoyaltyRule::TYPE_UPGRADE,
            ],
            [
                'name' => 'Level 1 on registration',
                'min_completed_orders' => 0,
                'min_lifetime_spend' => 0,
                'min_period_orders' => 0,
                'min_period_spend' => 0,
                'period_days' => 30,
                'allow_downgrade' => false,
                'is_active' => true,
                'priority' => 1,
                'metadata' => json_encode([
                    'evaluation_mode' => 'spend_duration',
                    'qualification_window' => 'registration',
                    'grant_on_registration' => true,
                    'benefit_duration_months' => null,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        if (! Schema::hasTable('loyalty_benefits')) {
            return;
        }

        DB::table('loyalty_benefits')->updateOrInsert(
            [
                'tier_id' => $levelOneId,
                'code' => 'level_1_discount',
            ],
            [
                'name' => 'Level 1 discount',
                'description' => 'Permanent starter loyalty discount applied after registration or login.',
                'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                'value' => 3,
                'configuration' => json_encode(['applies_to' => ['flight', 'hotel', 'insurance', 'esim']]),
                'display_order' => 1,
                'is_highlighted' => true,
                'is_active' => true,
                'metadata' => json_encode([
                    'evaluation_mode' => 'spend_duration',
                    'grant_on_registration' => true,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        // Data-only migration.
    }
};
