<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'provider_wallet_transactions';

    private const LEGACY_UNIQUE = 'provider_wallet_transactions_provider_wallet_id_reference_unique';

    private const WALLET_ID_INDEX = 'provider_wallet_transactions_wallet_id_index';

    private const NEW_UNIQUE = 'provider_wallet_tx_reference_unique';

    private const REFERENCE_LOOKUP_INDEX = 'provider_wallet_transactions_reference_type_reference_id_index';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'reference_type')) {
                $table->string('reference_type', 80)->nullable()->after('currency');
            }

            if (! Schema::hasColumn(self::TABLE, 'reference_id')) {
                $table->string('reference_id', 120)->nullable()->after('reference_type');
            }

            if (! Schema::hasColumn(self::TABLE, 'description')) {
                $table->string('description', 500)->nullable()->after('reference_id');
            }
        });

        if (Schema::hasColumn(self::TABLE, 'reference')) {
            $rows = DB::table(self::TABLE)->get();

            foreach ($rows as $row) {
                $referenceType = 'manual';
                $referenceId = null;
                $description = $row->note;

                if ($row->order_id) {
                    $referenceType = 'order';
                    $referenceId = (string) $row->order_id;
                } elseif (is_string($row->reference) && str_starts_with($row->reference, 'order_debit:')) {
                    $referenceType = 'order';
                    $referenceId = substr($row->reference, strlen('order_debit:'));
                } elseif (is_string($row->reference) && str_starts_with($row->reference, 'deposit:')) {
                    $referenceType = 'manual';
                    $referenceId = substr($row->reference, strlen('deposit:'));
                } elseif (is_string($row->reference) && str_starts_with($row->reference, 'adjustment:')) {
                    $referenceType = 'manual';
                    $referenceId = substr($row->reference, strlen('adjustment:'));
                } elseif (is_string($row->reference) && $row->reference !== '') {
                    $referenceId = $row->reference;
                }

                DB::table(self::TABLE)->where('id', $row->id)->update([
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'description' => $description,
                ]);
            }
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            if ($this->indexExists(self::TABLE, self::LEGACY_UNIQUE)) {
                if (! $this->indexExists(self::TABLE, self::WALLET_ID_INDEX)) {
                    $table->index('provider_wallet_id', self::WALLET_ID_INDEX);
                }

                $table->dropUnique(['provider_wallet_id', 'reference']);
            }

            if (Schema::hasColumn(self::TABLE, 'reference')) {
                $table->dropColumn(['reference', 'note']);
            }

            if (! $this->indexExists(self::TABLE, self::NEW_UNIQUE)) {
                $table->unique(
                    ['provider_wallet_id', 'reference_type', 'reference_id'],
                    self::NEW_UNIQUE,
                );
            }

            if (! $this->indexExists(self::TABLE, self::REFERENCE_LOOKUP_INDEX)) {
                $table->index(['reference_type', 'reference_id'], self::REFERENCE_LOOKUP_INDEX);
            }
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            if ($this->indexExists(self::TABLE, self::NEW_UNIQUE)) {
                $table->dropUnique(self::NEW_UNIQUE);
            }

            if (! Schema::hasColumn(self::TABLE, 'reference')) {
                $table->string('reference', 160)->nullable();
            }

            if (! Schema::hasColumn(self::TABLE, 'note')) {
                $table->string('note', 500)->nullable();
            }
        });

        $rows = DB::table(self::TABLE)->get();

        foreach ($rows as $row) {
            $reference = match ($row->reference_type) {
                'order' => 'order_debit:'.($row->reference_id ?? ''),
                default => ($row->type === 'deposit' ? 'deposit:' : 'adjustment:').($row->reference_id ?? $row->id),
            };

            DB::table(self::TABLE)->where('id', $row->id)->update([
                'reference' => $reference,
                'note' => $row->description,
            ]);
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            if ($this->indexExists(self::TABLE, self::REFERENCE_LOOKUP_INDEX)) {
                $table->dropIndex(self::REFERENCE_LOOKUP_INDEX);
            }

            if (Schema::hasColumn(self::TABLE, 'reference_type')) {
                $table->dropColumn(['reference_type', 'reference_id', 'description']);
            }

            if (! $this->indexExists(self::TABLE, self::LEGACY_UNIQUE)) {
                $table->unique(['provider_wallet_id', 'reference'], self::LEGACY_UNIQUE);
            }

            if ($this->indexExists(self::TABLE, self::WALLET_ID_INDEX)) {
                $table->dropIndex(self::WALLET_ID_INDEX);
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($item): bool => ($item->name ?? null) === $index);
        }

        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index],
        );

        return (int) ($result[0]->aggregate ?? 0) > 0;
    }
};
