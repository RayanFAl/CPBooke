<?php

use App\Models\LoyaltyBenefit;
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
        Schema::create('loyalty_benefits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tier_id')->constrained('loyalty_tiers')->cascadeOnDelete();
            $table->string('code', 80)->index();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('benefit_type', 40)->index();
            $table->string('value_type', 40)->nullable();
            $table->decimal('value', 12, 2)->nullable();
            $table->json('configuration')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_highlighted')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tier_id', 'is_active', 'display_order']);
        });

        $tiers = DB::table('loyalty_tiers')->pluck('id', 'level');

        DB::table('loyalty_benefits')->insert([
            [
                'tier_id' => $tiers[1],
                'code' => 'discount_5_percent',
                'name' => '5% loyalty discount',
                'description' => 'A lightweight loyalty discount for regular users.',
                'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                'value' => 5,
                'configuration' => json_encode(['applies_to' => ['flight', 'hotel', 'insurance']]),
                'display_order' => 1,
                'is_highlighted' => true,
                'is_active' => true,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[1],
                'code' => 'faster_support',
                'name' => 'Faster support handling',
                'description' => 'Tickets from this tier enter a slightly faster support lane.',
                'benefit_type' => LoyaltyBenefit::TYPE_SUPPORT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_TEXT,
                'value' => null,
                'configuration' => json_encode(['target_first_response_hours' => 8]),
                'display_order' => 2,
                'is_highlighted' => false,
                'is_active' => true,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[2],
                'code' => 'discount_10_percent',
                'name' => '10% loyalty discount',
                'description' => 'Higher recurring-customer discount.',
                'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                'value' => 10,
                'configuration' => json_encode(['applies_to' => ['flight', 'hotel', 'insurance']]),
                'display_order' => 1,
                'is_highlighted' => true,
                'is_active' => true,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[2],
                'code' => 'priority_support',
                'name' => 'Priority support',
                'description' => 'Priority ticket routing and higher SLA treatment.',
                'benefit_type' => LoyaltyBenefit::TYPE_SUPPORT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_TEXT,
                'value' => null,
                'configuration' => json_encode(['target_first_response_hours' => 4]),
                'display_order' => 2,
                'is_highlighted' => true,
                'is_active' => true,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[2],
                'code' => 'special_offers',
                'name' => 'Special offers',
                'description' => 'Access to exclusive campaigns and limited inventory offers.',
                'benefit_type' => LoyaltyBenefit::TYPE_OFFER,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_FLAG,
                'value' => 1,
                'configuration' => json_encode(['offer_segment' => 'active_customers']),
                'display_order' => 3,
                'is_highlighted' => false,
                'is_active' => true,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[3],
                'code' => 'exclusive_discount',
                'name' => 'Exclusive pricing',
                'description' => 'Preferred pricing for premium customers.',
                'benefit_type' => LoyaltyBenefit::TYPE_DISCOUNT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_PERCENTAGE,
                'value' => 15,
                'configuration' => json_encode(['applies_to' => ['flight', 'hotel']]),
                'display_order' => 1,
                'is_highlighted' => true,
                'is_active' => true,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[3],
                'code' => 'potential_free_upgrade',
                'name' => 'Potential free upgrade',
                'description' => 'Orders can be routed for complimentary upgrade consideration.',
                'benefit_type' => LoyaltyBenefit::TYPE_UPGRADE,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_FLAG,
                'value' => 1,
                'configuration' => json_encode(['review_channel' => 'operations']),
                'display_order' => 2,
                'is_highlighted' => true,
                'is_active' => true,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[3],
                'code' => 'faster_order_handling',
                'name' => 'Faster order handling',
                'description' => 'Operational handling is escalated for this tier.',
                'benefit_type' => LoyaltyBenefit::TYPE_SERVICE,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_TEXT,
                'value' => null,
                'configuration' => json_encode(['sla_priority' => 'vip']),
                'display_order' => 3,
                'is_highlighted' => false,
                'is_active' => true,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[4],
                'code' => 'vip_handling',
                'name' => 'VIP handling',
                'description' => 'Concierge-grade handling and escalation.',
                'benefit_type' => LoyaltyBenefit::TYPE_SERVICE,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_TEXT,
                'value' => null,
                'configuration' => json_encode(['sla_priority' => 'elite']),
                'display_order' => 1,
                'is_highlighted' => true,
                'is_active' => true,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[4],
                'code' => 'best_offers',
                'name' => 'Best available offers',
                'description' => 'Highest-value campaigns and negotiated rates.',
                'benefit_type' => LoyaltyBenefit::TYPE_OFFER,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_FLAG,
                'value' => 1,
                'configuration' => json_encode(['offer_segment' => 'elite_customers']),
                'display_order' => 2,
                'is_highlighted' => true,
                'is_active' => true,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier_id' => $tiers[4],
                'code' => 'instant_support',
                'name' => 'Instant support',
                'description' => 'Immediate support lane for elite customers.',
                'benefit_type' => LoyaltyBenefit::TYPE_SUPPORT,
                'value_type' => LoyaltyBenefit::VALUE_TYPE_TEXT,
                'value' => null,
                'configuration' => json_encode(['target_first_response_hours' => 1]),
                'display_order' => 3,
                'is_highlighted' => false,
                'is_active' => true,
                'metadata' => json_encode([]),
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
        Schema::dropIfExists('loyalty_benefits');
    }
};