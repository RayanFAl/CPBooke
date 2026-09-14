<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }

            if (! Schema::hasColumn('users', 'account_deleted_at')) {
                $table->timestamp('account_deleted_at')->nullable()->after('updated_at');
                $table->index('account_deleted_at');
            }

            if (! Schema::hasColumn('users', 'deletion_scheduled_at')) {
                $table->timestamp('deletion_scheduled_at')->nullable()->after('account_deleted_at');
            }

            if (! Schema::hasColumn('users', 'deletion_due_at')) {
                $table->timestamp('deletion_due_at')->nullable()->after('deletion_scheduled_at');
                $table->index('deletion_due_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'deletion_due_at')) {
                $table->dropIndex(['deletion_due_at']);
                $table->dropColumn('deletion_due_at');
            }

            if (Schema::hasColumn('users', 'deletion_scheduled_at')) {
                $table->dropColumn('deletion_scheduled_at');
            }

            if (Schema::hasColumn('users', 'account_deleted_at')) {
                $table->dropIndex(['account_deleted_at']);
                $table->dropColumn('account_deleted_at');
            }

            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
