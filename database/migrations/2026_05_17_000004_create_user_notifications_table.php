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
        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('notification_log_id')->nullable()->constrained('notification_logs')->nullOnDelete();
            $table->string('template_code', 120)->nullable()->index();
            $table->string('type', 80)->nullable()->index();
            $table->string('title', 255)->nullable();
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('related_type', 120)->nullable()->index();
            $table->unsignedBigInteger('related_id')->nullable()->index();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};