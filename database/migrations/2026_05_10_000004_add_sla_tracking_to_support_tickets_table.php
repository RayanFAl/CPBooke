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
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_tickets', 'first_response_due_at')) {
                $table->timestamp('first_response_due_at')->nullable()->after('description');
            }

            if (! Schema::hasColumn('support_tickets', 'resolution_due_at')) {
                $table->timestamp('resolution_due_at')->nullable()->after('first_response_due_at');
            }

            if (! Schema::hasColumn('support_tickets', 'first_response_at')) {
                $table->timestamp('first_response_at')->nullable()->after('resolution_due_at');
            }

            if (! Schema::hasColumn('support_tickets', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('first_response_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('support_tickets') || ! Schema::hasColumn('support_tickets', 'first_response_at')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn('first_response_at');
        });
    }
};