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
        if (Schema::hasTable('support_ticket_resolution_reports')) {
            return;
        }

        Schema::create('support_ticket_resolution_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_type');
            $table->text('root_cause')->nullable();
            $table->text('actions_taken')->nullable();
            $table->text('resolution_summary');
            $table->text('internal_notes')->nullable();
            $table->text('customer_visible_notes')->nullable();
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();
            $table->unsignedInteger('handling_minutes')->default(0);
            $table->boolean('escalated')->default(false);
            $table->unsignedInteger('reopened_count')->default(0);
            $table->boolean('satisfaction_requested')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique('ticket_id', 'ticket_id_unique');
            $table->index(['resolution_type', 'resolved_at'], 'res_type_resolved_idx');
            $table->index(['agent_id', 'resolved_at'], 'agent_resolved_idx');
            $table->index(['escalated', 'resolved_at'], 'escalated_resolved_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_resolution_reports');
    }
};