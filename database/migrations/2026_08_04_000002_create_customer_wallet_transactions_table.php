<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_wallet_id')->constrained('customer_wallets')->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('currency', 3);
            $table->string('reference_type', 80);
            $table->string('reference_id', 120);
            $table->string('description', 500)->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['customer_wallet_id', 'reference_type', 'reference_id'],
                'customer_wallet_tx_reference_unique',
            );
            $table->index(['order_id', 'type']);
            $table->index(['created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_transactions');
    }
};
