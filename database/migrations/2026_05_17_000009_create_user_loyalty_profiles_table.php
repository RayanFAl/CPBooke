<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_loyalty_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('current_tier_id')->nullable()->constrained('loyalty_tiers')->nullOnDelete();
            $table->foreignId('next_tier_id')->nullable()->constrained('loyalty_tiers')->nullOnDelete();
            $table->unsignedInteger('lifetime_orders_count')->default(0);
            $table->unsignedInteger('completed_orders_count')->default(0);
            $table->decimal('lifetime_spend', 12, 2)->default(0);
            $table->unsignedInteger('period_orders_count')->default(0);
            $table->decimal('period_spend', 12, 2)->default(0);
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->boolean('auto_upgrade_enabled')->default(true)->index();
            $table->timestamp('last_calculated_at')->nullable()->index();
            $table->timestamp('upgraded_at')->nullable()->index();
            $table->timestamp('downgraded_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['current_tier_id', 'last_calculated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_loyalty_profiles');
    }
};