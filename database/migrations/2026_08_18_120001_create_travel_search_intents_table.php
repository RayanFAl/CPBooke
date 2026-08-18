<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_search_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('origin', 64);
            $table->string('destination', 64);
            $table->string('route_key', 191);
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->decimal('last_seen_price', 12, 2)->nullable();
            $table->decimal('previous_seen_price', 12, 2)->nullable();
            $table->string('currency', 8)->default('LYD');
            $table->timestamp('last_searched_at');
            $table->timestamp('abandoned_notified_at')->nullable();
            $table->timestamp('price_drop_notified_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'route_key']);
            $table->index(['last_searched_at', 'converted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_search_intents');
    }
};
