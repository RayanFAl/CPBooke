<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_api_logs', function (Blueprint $table): void {
            $table->string('correlation_id', 80)->nullable()->after('provider_id')->index();
            $table->string('reference_type', 40)->nullable()->after('response_time_ms');
            $table->string('reference_id', 120)->nullable()->after('reference_type');
            $table->json('context')->nullable()->after('response_body');

            $table->index(['provider_id', 'success', 'occurred_at'], 'provider_api_logs_provider_success_occurred_idx');
            $table->index(['provider_id', 'service', 'success'], 'provider_api_logs_provider_service_success_idx');
        });
    }

    public function down(): void
    {
        Schema::table('provider_api_logs', function (Blueprint $table): void {
            $table->dropIndex('provider_api_logs_provider_success_occurred_idx');
            $table->dropIndex('provider_api_logs_provider_service_success_idx');
            $table->dropColumn(['correlation_id', 'reference_type', 'reference_id', 'context']);
        });
    }
};
