<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('loyalty_enabled')->default(true)->index();
            $table->boolean('auto_upgrade_enabled')->default(true)->index();
            $table->boolean('auto_downgrade_enabled')->default(false)->index();
            $table->boolean('visible_in_mobile_app')->default(true)->index();
            $table->boolean('allow_discount_stacking')->default(false)->index();
            $table->decimal('max_global_discount_amount', 12, 2)->nullable();
            $table->decimal('minimum_discountable_order_amount', 12, 2)->nullable();
            $table->string('default_currency', 3)->nullable()->index();
            $table->unsignedInteger('settings_version')->default(1);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
    }
};