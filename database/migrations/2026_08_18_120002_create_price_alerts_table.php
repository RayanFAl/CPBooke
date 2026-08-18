<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('origin', 64);
            $table->string('destination', 64);
            $table->string('route_key', 191);
            $table->date('departure_date')->nullable();
            $table->decimal('target_price', 12, 2);
            $table->decimal('last_triggered_price', 12, 2)->nullable();
            $table->string('currency', 8)->default('LYD');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'route_key', 'target_price']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
    }
};
