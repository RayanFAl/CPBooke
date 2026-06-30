<?php

use App\Models\NotificationLog;
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
        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 40)->index();
            $table->string('template_code', 120)->index();
            $table->string('event_class', 191)->nullable()->index();
            $table->string('notification_type', 80)->nullable()->index();
            $table->string('subject', 255)->nullable();
            $table->text('body');
            $table->json('variables')->nullable();
            $table->string('status', 20)->default(NotificationLog::STATUS_PENDING)->index();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->string('related_type', 120)->nullable()->index();
            $table->unsignedBigInteger('related_id')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'status', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
