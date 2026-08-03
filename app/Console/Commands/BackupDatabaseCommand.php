<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database
        {--keep=14 : Number of daily dumps to retain}
        {--disk=local : Relative directory under storage/app}';

    protected $description = 'Create a MySQL dump under storage/app/backups and prune old dumps.';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error('backup:database currently supports the mysql driver only.');

            return self::FAILURE;
        }

        $relativeDisk = trim((string) $this->option('disk'), '/');
        $directory = storage_path('app/'.($relativeDisk !== '' ? $relativeDisk.'/' : '').'backups');
        File::ensureDirectoryExists($directory);

        $filename = sprintf(
            '%s_%s.sql.gz',
            $config['database'] ?? 'database',
            now()->format('Y-m-d_His'),
        );
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        $command = [
            'mysqldump',
            '--single-transaction',
            '--routines',
            '--triggers',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? 'root'),
            $config['database'],
        ];

        $process = new Process($command);
        $process->setTimeout(600);
        $process->setEnv([
            'MYSQL_PWD' => (string) ($config['password'] ?? ''),
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('mysqldump failed: '.$process->getErrorOutput());

            return self::FAILURE;
        }

        $compressed = gzencode($process->getOutput(), 9);
        if ($compressed === false) {
            $this->error('Failed to compress dump.');

            return self::FAILURE;
        }

        File::put($path, $compressed);
        $this->info('Backup written: '.$path);

        $keep = max(1, (int) $this->option('keep'));
        $dumps = collect(File::files($directory))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.sql.gz'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        foreach ($dumps->slice($keep) as $old) {
            File::delete($old->getPathname());
            $this->line('Pruned: '.$old->getFilename());
        }

        return self::SUCCESS;
    }
}
