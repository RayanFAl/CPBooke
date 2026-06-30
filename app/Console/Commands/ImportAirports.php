<?php

namespace App\Console\Commands;

use App\Services\BooknowAirportsImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportAirports extends Command
{
    protected $signature = 'import:airports
                            {file : Path to the .xlsx or .csv airports file}
                            {--fresh : Truncate booknow_airports before importing}
                            {--limit= : Import only the first N rows (for testing)}';

    protected $description = 'Import airports into booknow_airports from Excel/CSV with UTF-8 support';

    public function handle(BooknowAirportsImportService $importService): int
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(0);

        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        if (! Schema::hasTable('booknow_airports')) {
            $this->error('The booknow_airports table does not exist. Run migrations first.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if (! $this->confirm('This will delete all existing booknow_airports records. Continue?')) {
                $this->info('Import cancelled.');

                return self::SUCCESS;
            }

            DB::table('booknow_airports')->truncate();
            $this->warn('booknow_airports table truncated.');
        }

        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? (int) $limit : null;

        $this->info('Starting import...');
        $this->line('File: '.$file);

        if ($limit !== null) {
            $this->line("Limit: {$limit} rows");
        }

        $startedAt = microtime(true);

        $stats = $importService->import(
            $file,
            $limit,
            function (int $processed): void {
                $this->output->write("\rProcessed: {$processed}");
            },
        );

        $duration = round(microtime(true) - $startedAt, 2);

        $this->newLine(2);
        $this->info('Import completed.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Inserted', $stats['imported']],
                ['Updated', $stats['updated']],
                ['Skipped', $stats['skipped']],
                ['Duration (seconds)', $duration],
            ],
        );

        $sample = DB::table('booknow_airports')
            ->whereNotNull('country_name_ar')
            ->where('country_name_ar', '!=', '')
            ->value('country_name_ar');

        if ($sample) {
            $this->line('Arabic sample check: '.$sample);
        }

        return self::SUCCESS;
    }
}
