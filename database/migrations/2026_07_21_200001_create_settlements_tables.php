<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('currency', 10)->default('LYD');
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('expected_cost', 14, 2)->default(0);
            $table->decimal('wallet_debit_total', 14, 2)->default(0);
            $table->decimal('supplier_invoice_total', 14, 2)->default(0);
            $table->decimal('difference', 14, 2)->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('compared_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'period_start', 'period_end', 'currency'], 'settlements_provider_period_unique');
            $table->index(['provider_id', 'status']);
        });

        Schema::create('settlement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('booking_reference')->nullable()->index();
            $table->string('external_booking_id')->nullable();
            $table->decimal('supplier_cost', 14, 2)->nullable();
            $table->decimal('wallet_debit', 14, 2)->nullable();
            $table->decimal('supplier_invoice_cost', 14, 2)->nullable();
            $table->decimal('difference', 14, 2)->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['settlement_id', 'status']);
            $table->unique(['settlement_id', 'order_id'], 'settlement_items_settlement_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlements');
    }
};
