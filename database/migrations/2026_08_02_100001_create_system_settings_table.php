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
            $table->string('company_legal_name')->nullable();
            $table->string('company_display_name')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone', 40)->nullable();
            $table->string('website_url')->nullable();
            $table->string('tax_id', 80)->nullable();
            $table->text('company_address')->nullable();
            $table->string('default_currency', 3)->default('LYD')->index();
            $table->string('timezone', 64)->default('Africa/Tripoli');
            $table->string('default_locale', 12)->default('en');
            $table->decimal('default_margin_percent', 8, 2)->nullable();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->boolean('push_enabled')->default(true);
            $table->string('mail_from_name')->nullable();
            $table->string('sms_sender_id', 40)->nullable();
            $table->boolean('maintenance_mode')->default(false)->index();
            $table->boolean('support_chat_enabled')->default(true);
            $table->boolean('orders_legacy_create_enabled')->default(false);
            $table->boolean('home_offers_enabled')->default(true);
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
