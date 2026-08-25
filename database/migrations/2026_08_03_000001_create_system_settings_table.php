<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('company_name')->nullable();
                $table->string('company_address')->nullable();
                $table->string('support_email')->nullable();
                $table->string('support_phone')->nullable();
                $table->string('tax_id')->nullable();
                $table->string('logo_path')->nullable();
                $table->string('default_currency', 3)->default('LYD')->index();
                $table->string('timezone')->default('Africa/Tripoli');
                $table->string('locale', 16)->default('en');
                $table->decimal('default_commission_percent', 8, 2)->nullable();
                $table->boolean('channel_email_enabled')->default(true);
                $table->boolean('channel_sms_enabled')->default(true);
                $table->boolean('channel_whatsapp_enabled')->default(true);
                $table->boolean('channel_push_enabled')->default(true);
                $table->string('email_from_name')->nullable();
                $table->string('sms_sender_name')->nullable();
                $table->string('whatsapp_sender_name')->nullable();
                $table->boolean('feature_maintenance_mode')->default(false)->index();
                $table->boolean('feature_chat_enabled')->default(true);
                $table->boolean('feature_legacy_order_create')->default(false);
                $table->unsignedInteger('settings_version')->default(1);
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('system_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_settings', 'company_name')) {
                $table->string('company_name')->nullable();
            }
            if (! Schema::hasColumn('system_settings', 'logo_path')) {
                $table->string('logo_path')->nullable();
            }
            if (! Schema::hasColumn('system_settings', 'locale')) {
                $table->string('locale', 16)->default('en');
            }
            if (! Schema::hasColumn('system_settings', 'default_commission_percent')) {
                $table->decimal('default_commission_percent', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('system_settings', 'channel_email_enabled')) {
                $table->boolean('channel_email_enabled')->default(true);
            }
            if (! Schema::hasColumn('system_settings', 'channel_sms_enabled')) {
                $table->boolean('channel_sms_enabled')->default(true);
            }
            if (! Schema::hasColumn('system_settings', 'channel_whatsapp_enabled')) {
                $table->boolean('channel_whatsapp_enabled')->default(true);
            }
            if (! Schema::hasColumn('system_settings', 'channel_push_enabled')) {
                $table->boolean('channel_push_enabled')->default(true);
            }
            if (! Schema::hasColumn('system_settings', 'email_from_name')) {
                $table->string('email_from_name')->nullable();
            }
            if (! Schema::hasColumn('system_settings', 'sms_sender_name')) {
                $table->string('sms_sender_name')->nullable();
            }
            if (! Schema::hasColumn('system_settings', 'whatsapp_sender_name')) {
                $table->string('whatsapp_sender_name')->nullable();
            }
            if (! Schema::hasColumn('system_settings', 'feature_maintenance_mode')) {
                $table->boolean('feature_maintenance_mode')->default(false);
            }
            if (! Schema::hasColumn('system_settings', 'feature_chat_enabled')) {
                $table->boolean('feature_chat_enabled')->default(true);
            }
            if (! Schema::hasColumn('system_settings', 'feature_legacy_order_create')) {
                $table->boolean('feature_legacy_order_create')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
