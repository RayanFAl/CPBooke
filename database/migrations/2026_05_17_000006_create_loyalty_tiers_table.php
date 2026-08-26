<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loyalty_tiers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('level')->unique();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('badge_label', 120)->nullable();
            $table->string('color_token', 40)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        DB::table('loyalty_tiers')->insert([
            [
                'level' => 0,
                'code' => 'new_user',
                'name' => 'New User',
                'badge_label' => 'Level 0',
                'color_token' => 'slate',
                'description' => 'Entry level for customers who have not unlocked loyalty privileges yet.',
                'sort_order' => 0,
                'is_active' => true,
                'is_default' => true,
                'metadata' => json_encode(['program_label' => 'BookNow Loyalty']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'level' => 1,
                'code' => 'regular',
                'name' => 'Regular',
                'badge_label' => 'Level 1',
                'color_token' => 'emerald',
                'description' => 'Unlocked after the first meaningful booking streak.',
                'sort_order' => 1,
                'is_active' => true,
                'is_default' => false,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'level' => 2,
                'code' => 'active',
                'name' => 'Active',
                'badge_label' => 'Level 2',
                'color_token' => 'amber',
                'description' => 'For customers with sustained recurring booking activity.',
                'sort_order' => 2,
                'is_active' => true,
                'is_default' => false,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'level' => 3,
                'code' => 'vip',
                'name' => 'VIP',
                'badge_label' => 'Level 3',
                'color_token' => 'rose',
                'description' => 'High-value customers with strong retention and spend.',
                'sort_order' => 3,
                'is_active' => true,
                'is_default' => false,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'level' => 4,
                'code' => 'elite',
                'name' => 'Elite',
                'badge_label' => 'Level 4',
                'color_token' => 'sky',
                'description' => 'Optional highest tier with concierge-grade handling.',
                'sort_order' => 4,
                'is_active' => true,
                'is_default' => false,
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_tiers');
    }
};