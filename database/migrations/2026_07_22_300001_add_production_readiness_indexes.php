<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index('provider_name');
                $table->index(['status', 'id']);
                $table->index(['customer_id', 'id']);
                $table->index(['payment_status', 'id']);
            });
        }

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                if (Schema::hasColumn('support_tickets', 'first_response_due_at')) {
                    $table->index('first_response_due_at');
                }

                if (Schema::hasColumn('support_tickets', 'resolution_due_at')) {
                    $table->index('resolution_due_at');
                }

                $table->index(['status', 'updated_at']);
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->index(['actor_id', 'created_at']);
                $table->index(['status', 'created_at']);
            });
        }

        if (Schema::hasTable('application_events')) {
            Schema::table('application_events', function (Blueprint $table): void {
                $table->index('source');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropIndex(['provider_name']);
                $table->dropIndex(['status', 'id']);
                $table->dropIndex(['customer_id', 'id']);
                $table->dropIndex(['payment_status', 'id']);
            });
        }

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                if (Schema::hasColumn('support_tickets', 'first_response_due_at')) {
                    $table->dropIndex(['first_response_due_at']);
                }

                if (Schema::hasColumn('support_tickets', 'resolution_due_at')) {
                    $table->dropIndex(['resolution_due_at']);
                }

                $table->dropIndex(['status', 'updated_at']);
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->dropIndex(['actor_id', 'created_at']);
                $table->dropIndex(['status', 'created_at']);
            });
        }

        if (Schema::hasTable('application_events')) {
            Schema::table('application_events', function (Blueprint $table): void {
                $table->dropIndex(['source']);
            });
        }
    }
};
