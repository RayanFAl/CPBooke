<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('airport_stats')) {
            return;
        }

        Schema::create('airport_stats', function (Blueprint $table) {
            $table->id();
            $table->string('airport_key', 32)->unique();
            $table->unsignedInteger('search_count')->default(0);
            $table->unsignedInteger('travel_count')->default(0);
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamp('last_traveled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airport_stats');
    }
};
