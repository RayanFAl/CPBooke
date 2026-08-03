<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();

            // Company
            $table->string('company_name')->nullable();
            $table->string('company_address')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('logo_path')->nullable();

            // Localization
            $table->string('default_currency', 3)->default('LYD')->index();
            $table->string('timezone')->default('Africa/Tripoli');
            $table->string('locale', 16)->default('en');

            // Commercial margin (platform default commission %)
            $table->decimal('default_commission_percent', 8, 2)->nullable();

            // Channels (tokens stay in .env; these are operational switches + display names)
            $table->boolean('channel_email_enabled')->default(true);
            $table->boolean('channel_sms_enabled')->default(true);
            $table->boolean('channel_whatsapp_enabled')->default(true);
            $table->boolean('channel_push_enabled')->default(true);
            $table->string('email_from_name')->nullable();
            $table->string('sms_sender_name')->nullable();
            $table->string('whatsapp_sender_name')->nullable();

            // Feature flags
            $table->boolean('feature_maintenance_mode')->default(false)->index();
            $table->boolean('feature_chat_enabled')->default(true);
            $table->boolean('feature_legacy_order_create')->default(false);

            $table->unsignedInteger('settings_version')->default(1);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
