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
        Schema::table('support_messages', function (Blueprint $table): void {
            $table->string('sender_type')->nullable()->after('is_internal');
            $table->string('message_type')->nullable()->after('sender_type');
            $table->json('metadata')->nullable()->after('message_type');
            $table->foreignId('reply_to_id')->nullable()->after('metadata')->constrained('support_messages')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable()->after('reply_to_id');
            $table->timestamp('seen_at')->nullable()->after('delivered_at');

            $table->index(['support_ticket_id', 'sender_type', 'created_at'], 'support_messages_ticket_sender_created_idx');
            $table->index(['support_ticket_id', 'seen_at', 'created_at'], 'support_messages_ticket_seen_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table): void {
            $table->dropIndex('support_messages_ticket_sender_created_idx');
            $table->dropIndex('support_messages_ticket_seen_created_idx');
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropColumn([
                'sender_type',
                'message_type',
                'metadata',
                'delivered_at',
                'seen_at',
            ]);
        });
    }
};