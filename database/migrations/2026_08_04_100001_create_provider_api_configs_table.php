<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_api_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('environment', 20)->index();
            $table->string('base_url', 500)->nullable();
            $table->string('auth_type', 40)->default('api_key');
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('webhook_url', 500)->nullable();
            $table->unsignedSmallInteger('timeout')->default(30);
            $table->json('custom_headers')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable();
            $table->unsignedSmallInteger('last_test_http_status')->nullable();
            $table->string('last_test_message', 500)->nullable();
            $table->unsignedInteger('last_test_latency_ms')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_api_configs');
    }
};
