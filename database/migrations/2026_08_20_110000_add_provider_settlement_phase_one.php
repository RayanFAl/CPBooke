<?php

use App\Support\EnsuresInnoDbStorageEngine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        EnsuresInnoDbStorageEngine::apply(
            'users',
            'settlements',
            'settlement_items',
            'approvals',
            'financial_transactions',
        );

        Schema::table('settlements', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable()->after('compared_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->after('closed_at');
            $table->foreignId('reopened_by')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable()->after('reopened_by');
            $table->json('close_history')->nullable()->after('reopen_reason');
        });

        Schema::table('settlement_items', function (Blueprint $table): void {
            $table->string('expected_cost_source', 30)->default('order')->after('supplier_cost');
            $table->string('resolution_type', 30)->nullable()->after('status');
            $table->string('resolution_reason', 50)->nullable()->after('resolution_type');
            $table->decimal('resolution_amount', 14, 2)->nullable()->after('resolution_reason');
            $table->foreignId('pending_approval_id')->nullable()->after('resolved_at')->constrained('approvals')->nullOnDelete();
            $table->foreignId('financial_transaction_id')->nullable()->after('pending_approval_id')->constrained('financial_transactions')->nullOnDelete();
        });

        Schema::create('settlement_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('settlement_id');
            $table->string('kind', 30)->index();
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('source', 30)->default('upload');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['settlement_id', 'kind']);
        });

        $this->ensureInnoDb('settlements');
        $this->ensureInnoDb('settlement_items');
        $this->ensureInnoDb('settlement_attachments');

        $this->ensureForeignKey('settlement_attachments', 'settlement_id', 'settlements', 'id', 'cascade');
        $this->ensureForeignKey('settlement_attachments', 'uploaded_by', 'users', 'id', 'set null');
    }

    private function ensureForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $onDelete,
    ): void {
        if ($this->foreignKeyExists($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable, $referencedColumn, $onDelete): void {
            $foreign = $blueprint->foreign($column)->references($referencedColumn)->on($referencedTable);

            match ($onDelete) {
                'cascade' => $foreign->cascadeOnDelete(),
                'set null' => $foreign->nullOnDelete(),
                default => $foreign,
            };
        });
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return false;
        }

        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, $table, $column],
        );

        return (int) ($result[0]->aggregate ?? 0) > 0;
    }

    private function ensureInnoDb(string $table): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $status = DB::selectOne('SHOW TABLE STATUS WHERE Name = ?', [$table]);

        if ($status && strtolower((string) ($status->Engine ?? '')) !== 'innodb') {
            DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_attachments');

        Schema::table('settlement_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('financial_transaction_id');
            $table->dropConstrainedForeignId('pending_approval_id');
            $table->dropColumn([
                'expected_cost_source',
                'resolution_type',
                'resolution_reason',
                'resolution_amount',
            ]);
        });

        Schema::table('settlements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn([
                'approved_at',
                'reopened_at',
                'reopen_reason',
                'close_history',
            ]);
        });
    }
};
