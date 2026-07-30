<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_wallets', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_key', 80);
            $table->string('provider_name', 120);
            $table->string('currency', 3)->default('LYD');
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('low_balance_threshold', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider_key', 'currency']);
            $table->index(['is_active', 'provider_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_wallets');
    }
};
