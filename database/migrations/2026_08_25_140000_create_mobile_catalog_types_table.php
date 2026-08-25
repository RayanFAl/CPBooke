<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_catalog_types', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->string('key', 80)->unique();
            $table->string('title_en', 160);
            $table->string('title_ar', 160)->nullable();
            $table->string('subtitle_en', 255)->nullable();
            $table->string('subtitle_ar', 255)->nullable();
            $table->string('options_image_path')->nullable();
            $table->string('options_image_url', 500)->nullable();
            $table->string('market_image_path')->nullable();
            $table->string('market_image_url', 500)->nullable();
            $table->boolean('show_in_options')->default(true);
            $table->boolean('show_in_market')->default(true);
            $table->string('action_type', 40)->default('route');
            $table->string('action_value', 500)->nullable();
            $table->json('action_payload')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('platforms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_catalog_types');
    }
};
