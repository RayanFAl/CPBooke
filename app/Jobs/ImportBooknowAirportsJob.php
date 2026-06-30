<?php

namespace App\Jobs;

use App\Services\BooknowAirportsImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportBooknowAirportsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public function __construct(
        public string $filePath,
        public bool $fresh,
        public int $userId,
    ) {
    }

    public function handle(BooknowAirportsImportService $importService): void
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(0);

        $this->updateStatus([
            'status' => 'processing',
            'message' => 'Import is running...',
            'started_at' => now()->toIso8601String(),
            'user_id' => $this->userId,
        ]);

        if ($this->fresh && Schema::hasTable('booknow_airports')) {
            DB::table('booknow_airports')->truncate();
        }

        $startedAt = microtime(true);

        $stats = $importService->import($this->filePath);

        $this->updateStatus([
            'status' => 'completed',
            'message' => 'Import completed successfully.',
            'stats' => $stats,
            'duration_seconds' => round(microtime(true) - $startedAt, 2),
            'finished_at' => now()->toIso8601String(),
            'user_id' => $this->userId,
        ]);

        if (is_file($this->filePath)) {
            File::delete($this->filePath);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->updateStatus([
            'status' => 'failed',
            'message' => $exception?->getMessage() ?: 'Import failed.',
            'finished_at' => now()->toIso8601String(),
            'user_id' => $this->userId,
        ]);

        if (is_file($this->filePath)) {
            File::delete($this->filePath);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateStatus(array $payload): void
    {
        Cache::put('booknow_airports_import_status', $payload, now()->addDay());
    }
}
