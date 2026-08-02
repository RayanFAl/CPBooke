<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 32)->default('active')->index();
            $table->string('contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('name');
            $table->string('key_prefix', 24)->index();
            $table->string('key_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('url');
            $table->text('signing_secret');
            $table->json('events');
            $table->boolean('is_active')->default(true)->index();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('partner_webhook_endpoint_id')->constrained('partner_webhook_endpoints')->cascadeOnDelete();
            $table->string('event', 64)->index();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->json('payload');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['partner_webhook_endpoint_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_webhook_deliveries');
        Schema::dropIfExists('partner_webhook_endpoints');
        Schema::dropIfExists('partner_api_keys');
        Schema::dropIfExists('partners');
    }
};
