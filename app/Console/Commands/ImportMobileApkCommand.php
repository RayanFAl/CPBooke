<?php

namespace App\Console\Commands;

use App\Modules\Admin\MobileApp\Services\MobileAppAdminService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ImportMobileApkCommand extends Command
{
    protected $signature = 'mobile-app:import-apk
                            {path : Absolute or relative path to the APK file}
                            {--version= : Semantic version like 1.0.0}
                            {--version-code= : Integer version code like 1}';

    protected $description = 'Import a local APK into storage/app/releases (useful when web upload hits PHP size limits)';

    public function handle(MobileAppAdminService $adminService): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("APK file not found: {$path}");

            return self::FAILURE;
        }

        $filename = basename($path);
        [$parsedVersion, $parsedVersionCode] = $this->parseVersionFromFilename($filename);

        $version = (string) ($this->option('version') ?: $parsedVersion ?: config('mobile_app.version', '1.0.0'));
        $versionCode = (int) ($this->option('version-code') ?: $parsedVersionCode ?: config('mobile_app.version_code', 1));

        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            $this->error('Version must use semantic format like 1.0.0.');

            return self::FAILURE;
        }

        if ($versionCode < 1) {
            $this->error('Version code must be at least 1.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists($adminService->releasesDirectory());

        $targetFilename = $adminService->buildApkFilename($version, $versionCode);
        $targetPath = $adminService->releasesDirectory().DIRECTORY_SEPARATOR.$targetFilename;

        if (! copy($path, $targetPath)) {
            $this->error("Could not copy APK to {$targetPath}");

            return self::FAILURE;
        }

        $existing = $adminService->readManifestForForm();

        $adminService->uploadApkFromPath($targetPath, $version, $versionCode, $existing);

        $this->info("Imported {$targetFilename} ({$this->formatBytes(filesize($targetPath))}).");

        return self::SUCCESS;
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function parseVersionFromFilename(string $filename): array
    {
        if (preg_match('/(?:^|[._-])v?(\d+\.\d+\.\d+)[+_\-](\d+)\.apk$/i', $filename, $matches) === 1) {
            return [$matches[1], (int) $matches[2]];
        }

        return [null, null];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return sprintf('%s %s', rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.'), $units[$unitIndex]);
    }
}
