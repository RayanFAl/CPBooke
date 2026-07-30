<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_api_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('event_type', 50)->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('success')->default(true)->index();
            $table->string('reference_type', 50)->nullable();
            $table->string('reference_id')->nullable();
            $table->string('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['provider_id', 'created_at']);
            $table->index(['provider_id', 'success', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_api_events');
    }
};
