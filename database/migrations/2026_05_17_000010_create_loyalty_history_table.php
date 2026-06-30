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
        Schema::create('loyalty_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_tier_id')->nullable()->constrained('loyalty_tiers')->nullOnDelete();
            $table->foreignId('to_tier_id')->nullable()->constrained('loyalty_tiers')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('action', 40)->index();
            $table->string('trigger_event_class', 191)->nullable()->index();
            $table->json('metrics_snapshot')->nullable();
            $table->json('rule_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_history');
    }
};