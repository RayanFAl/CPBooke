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
        Schema::table('saved_passengers', function (Blueprint $table): void {
            if (! Schema::hasColumn('saved_passengers', 'passport_number_hash')) {
                $table->string('passport_number_hash', 64)->nullable()->after('passport_number');
            }

            if (! Schema::hasColumn('saved_passengers', 'phone_hash')) {
                $table->string('phone_hash', 64)->nullable()->after('phone');
            }
        });

        Schema::table('saved_passengers', function (Blueprint $table): void {
            if (! $this->indexExists('saved_passengers', 'saved_passengers_user_id_passport_number_hash_index')) {
                $table->index(['user_id', 'passport_number_hash']);
            }

            if (! $this->indexExists('saved_passengers', 'saved_passengers_user_id_phone_hash_index')) {
                $table->index(['user_id', 'phone_hash']);
            }
        });
    }

    /**
     * Determine whether the given index exists on the table.
     */
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saved_passengers', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'passport_number_hash']);
            $table->dropIndex(['user_id', 'phone_hash']);
            $table->dropColumn(['passport_number_hash', 'phone_hash']);
        });
    }
};
