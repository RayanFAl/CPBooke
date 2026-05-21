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
        Schema::create('order_pricing_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('source_type', 60)->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('code', 120)->nullable()->index();
            $table->string('label', 160);
            $table->string('adjustment_type', 40)->index();
            $table->string('value_type', 40)->nullable()->index();
            $table->decimal('configured_value', 12, 2)->nullable();
            $table->decimal('applied_amount', 12, 2)->default(0);
            $table->string('currency', 3)->nullable()->index();
            $table->unsignedSmallInteger('priority')->default(0)->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['order_id', 'priority']);
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_pricing_adjustments');
    }
};