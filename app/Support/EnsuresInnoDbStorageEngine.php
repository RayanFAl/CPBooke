<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EnsuresInnoDbStorageEngine
{
    public static function apply(string ...$tables): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($tables as $table) {
            self::applyToTable($table);
        }
    }

    public static function applyAll(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $tables = DB::select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
            [$database, 'BASE TABLE'],
        );

        foreach ($tables as $table) {
            self::applyToTable($table->name);
        }
    }

    private static function applyToTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $status = DB::selectOne('SHOW TABLE STATUS WHERE Name = ?', [$table]);

        if ($status && strtolower((string) ($status->Engine ?? '')) !== 'innodb') {
            DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
        }
    }
}
