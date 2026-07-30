<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('check_key', 60)->index();
            $table->string('status', 20)->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['check_key', 'created_at']);
        });

        Schema::create('application_events', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 40)->index();
            $table->string('severity', 20)->default('info')->index();
            $table->string('source', 80)->nullable();
            $table->string('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['category', 'created_at']);
            $table->index(['severity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_events');
        Schema::dropIfExists('system_health_checks');
    }
};
