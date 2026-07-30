<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_wallet_id')->constrained('provider_wallets')->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('currency', 3);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('reference', 160)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider_wallet_id', 'reference']);
            $table->index(['order_id', 'type']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_wallet_transactions');
    }
};
