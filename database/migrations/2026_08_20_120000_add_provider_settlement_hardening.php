<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SETTLEMENT_TABLES = [
        'settlements',
        'settlement_items',
        'settlement_attachments',
        'settlement_invoice_imports',
    ];

    public function up(): void
    {
        foreach (self::SETTLEMENT_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $this->ensureInnoDb($table);
            }
        }

        if (! Schema::hasTable('settlement_invoice_imports')) {
            Schema::create('settlement_invoice_imports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('settlement_id');
                $table->unsignedInteger('sequence');
                $table->unsignedBigInteger('attachment_id')->nullable();
                $table->string('original_name')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamp('uploaded_at');
                $table->unsignedInteger('row_count')->default(0);
                $table->unsignedInteger('matched_count')->default(0);
                $table->unsignedInteger('extra_count')->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->json('errors')->nullable();
                $table->boolean('is_active')->default(false);
                $table->timestamps();

                $table->unique(['settlement_id', 'sequence']);
                $table->index(['settlement_id', 'is_active']);
            });

            $this->ensureInnoDb('settlement_invoice_imports');
        }

        $this->ensureForeignKey(
            'settlement_invoice_imports',
            'settlement_id',
            'settlements',
            'id',
            'cascade',
        );
        $this->ensureForeignKey(
            'settlement_invoice_imports',
            'attachment_id',
            'settlement_attachments',
            'id',
            'set null',
        );
        $this->ensureForeignKey(
            'settlement_invoice_imports',
            'uploaded_by',
            'users',
            'id',
            'set null',
        );

        Schema::table('settlements', function (Blueprint $table): void {
            if (! Schema::hasColumn('settlements', 'current_invoice_import_id')) {
                $table->unsignedBigInteger('current_invoice_import_id')->nullable()->after('close_history');
            }

            if (! Schema::hasColumn('settlements', 'close_snapshot')) {
                $table->json('close_snapshot')->nullable()->after('current_invoice_import_id');
            }

            if (! Schema::hasColumn('settlements', 'resolved_count')) {
                $table->unsignedInteger('resolved_count')->default(0)->after('matched_count');
            }

            if (! Schema::hasColumn('settlements', 'adjustment_total')) {
                $table->decimal('adjustment_total', 14, 2)->default(0)->after('difference');
            }
        });

        $this->ensureForeignKey(
            'settlements',
            'current_invoice_import_id',
            'settlement_invoice_imports',
            'id',
            'set null',
        );

        Schema::table('settlement_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('settlement_items', 'invoice_import_id')) {
                $table->unsignedBigInteger('invoice_import_id')->nullable()->after('financial_transaction_id');
            }
        });

        $this->ensureForeignKey(
            'settlement_items',
            'invoice_import_id',
            'settlement_invoice_imports',
            'id',
            'set null',
        );
    }

    public function down(): void
    {
        Schema::table('settlement_items', function (Blueprint $table): void {
            if (Schema::hasColumn('settlement_items', 'invoice_import_id')) {
                $table->dropConstrainedForeignId('invoice_import_id');
            }
        });

        Schema::table('settlements', function (Blueprint $table): void {
            if (Schema::hasColumn('settlements', 'current_invoice_import_id')) {
                $table->dropConstrainedForeignId('current_invoice_import_id');
            }

            $columns = array_filter([
                Schema::hasColumn('settlements', 'close_snapshot') ? 'close_snapshot' : null,
                Schema::hasColumn('settlements', 'resolved_count') ? 'resolved_count' : null,
                Schema::hasColumn('settlements', 'adjustment_total') ? 'adjustment_total' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('settlement_invoice_imports');
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

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $this->ensureInnoDb($table);
            $this->ensureInnoDb($referencedTable);
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
};
