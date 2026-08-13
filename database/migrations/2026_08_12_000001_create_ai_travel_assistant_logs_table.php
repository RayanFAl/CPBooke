<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_travel_assistant_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 32);
            $table->string('message', 500)->nullable();
            $table->string('intent', 64)->nullable();
            $table->string('product', 32)->nullable();
            $table->string('source', 32)->nullable();
            $table->boolean('fallback')->default(false);
            $table->string('fallback_reason', 64)->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->boolean('needs_clarification')->default(false);
            $table->json('missing_slots')->nullable();
            $table->json('slots_summary')->nullable();
            $table->unsignedSmallInteger('recommendations_count')->nullable();
            $table->unsignedSmallInteger('offers_count')->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('success')->default(true);
            $table->string('error_message', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['mode', 'source']);
            $table->index(['intent', 'fallback']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_travel_assistant_logs');
    }
};
