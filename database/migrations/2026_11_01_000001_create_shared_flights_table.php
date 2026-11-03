<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_flights', function (Blueprint $table): void {
            $table->string('token', 32)->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('search');
            $table->json('selected_offer');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_flights');
    }
};
