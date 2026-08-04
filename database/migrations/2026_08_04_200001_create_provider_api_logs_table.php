<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_api_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('service', 40)->index();
            $table->string('endpoint_key', 120)->index();
            $table->string('endpoint_label', 160);
            $table->string('endpoint_path', 500);
            $table->string('http_method', 10);
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->boolean('success')->index();
            $table->unsignedInteger('response_time_ms')->nullable()->index();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['provider_id', 'service', 'endpoint_key'], 'provider_api_logs_provider_service_endpoint_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_api_logs');
    }
};
