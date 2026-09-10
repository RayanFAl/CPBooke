<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('origin', 64);
            $table->string('destination', 64);
            $table->string('route_key', 191);
            $table->string('watch_key', 191);
            $table->date('departure_date')->nullable();
            $table->string('flight_number', 32)->nullable();
            $table->string('offer_id', 120)->nullable();
            $table->string('cabin', 32)->nullable();
            $table->unsignedSmallInteger('min_seats')->default(1);
            $table->unsignedSmallInteger('last_triggered_seats')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'watch_key', 'min_seats']);
            $table->index(['user_id', 'is_active']);
            $table->index(['route_key', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_alerts');
    }
};
