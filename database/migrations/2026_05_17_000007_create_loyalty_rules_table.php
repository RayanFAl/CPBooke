<?php

use App\Models\LoyaltyRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loyalty_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tier_id')->constrained('loyalty_tiers')->cascadeOnDelete();
            $table->string('rule_type', 40)->default(LoyaltyRule::TYPE_UPGRADE)->index();
            $table->string('name', 160);
            $table->unsignedInteger('min_completed_orders')->default(0);
            $table->decimal('min_lifetime_spend', 12, 2)->default(0);
            $table->unsignedInteger('min_period_orders')->default(0);
            $table->decimal('min_period_spend', 12, 2)->default(0);
            $table->unsignedSmallInteger('period_days')->default(365);
            $table->boolean('allow_downgrade')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedTinyInteger('priority')->default(0)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tier_id', 'rule_type', 'is_active']);
        });

        $tiers = DB::table('loyalty_tiers')->pluck('id', 'level');

        DB::table('loyalty_rules')->insert([
            [
                'tier_id' => $tiers[1],
                'rule_type' => LoyaltyRule::TYPE_UPGRADE,
                'name' => 'Regular tier rule',
                'min_completed_orders' => 5,
                'min_lifetime_spend' => 1000,
                'min_period_orders' => 3,
                'min_period_spend' => 500,
                'period_days' => 365,
                'allow_downgrade' => false,
                'is_active' => true,
                'priority' => 1,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[2],
                'rule_type' => LoyaltyRule::TYPE_UPGRADE,
                'name' => 'Active tier rule',
                'min_completed_orders' => 15,
                'min_lifetime_spend' => 3500,
                'min_period_orders' => 8,
                'min_period_spend' => 1800,
                'period_days' => 365,
                'allow_downgrade' => false,
                'is_active' => true,
                'priority' => 2,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[3],
                'rule_type' => LoyaltyRule::TYPE_UPGRADE,
                'name' => 'VIP tier rule',
                'min_completed_orders' => 30,
                'min_lifetime_spend' => 8000,
                'min_period_orders' => 15,
                'min_period_spend' => 4000,
                'period_days' => 365,
                'allow_downgrade' => false,
                'is_active' => true,
                'priority' => 3,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[4],
                'rule_type' => LoyaltyRule::TYPE_UPGRADE,
                'name' => 'Elite tier rule',
                'min_completed_orders' => 50,
                'min_lifetime_spend' => 15000,
                'min_period_orders' => 25,
                'min_period_spend' => 7000,
                'period_days' => 365,
                'allow_downgrade' => true,
                'is_active' => true,
                'priority' => 4,
                'metadata' => json_encode(['review_required' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_rules');
    }
};