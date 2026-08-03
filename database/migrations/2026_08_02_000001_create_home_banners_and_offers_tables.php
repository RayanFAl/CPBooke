<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_banners', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->string('title_en');
            $table->string('title_ar')->nullable();
            $table->string('subtitle_en')->nullable();
            $table->string('subtitle_ar')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_url')->nullable();
            $table->string('action_type', 40)->default('none');
            $table->string('action_value')->nullable();
            $table->json('action_payload')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('platforms')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('home_offers', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->string('title_en');
            $table->string('title_ar')->nullable();
            $table->string('subtitle_en')->nullable();
            $table->string('subtitle_ar')->nullable();
            $table->string('badge_en')->nullable();
            $table->string('badge_ar')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_url')->nullable();
            $table->string('accent_color', 20)->nullable();
            $table->string('category', 40)->default('other');
            $table->string('action_type', 40)->default('none');
            $table->string('action_value')->nullable();
            $table->json('action_payload')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('platforms')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['starts_at', 'ends_at']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_offers');
        Schema::dropIfExists('home_banners');
    }
};
