<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('wallet_number', 32)->unique();
            $table->string('currency', 3);
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['user_id', 'currency']);
            $table->index(['status', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallets');
    }
};
