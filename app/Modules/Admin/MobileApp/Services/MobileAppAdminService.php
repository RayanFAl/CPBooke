<?php

namespace App\Modules\Admin\MobileApp\Services;

use App\Modules\Content\Services\MobileAppReleaseService;
use App\Modules\Notifications\Jobs\BroadcastMobileAppReleaseNotificationsJob;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\NotificationTemplateSyncService;
use App\Support\PhpIniSize;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class MobileAppAdminService
{
    public function __construct(
        private readonly MobileAppReleaseService $releaseService,
    ) {}

    public function releasesDirectory(): string
    {
        return (string) config('mobile_app.releases_directory');
    }

    public function manifestPath(): string
    {
        return $this->releasesDirectory().DIRECTORY_SEPARATOR.'release.json';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readManifest(): ?array
    {
        $path = $this->manifestPath();

        if (! File::isFile($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{
     *     version: string,
     *     version_code: int,
     *     apk: string,
     *     force_update: bool,
     *     min_version_code: int|null,
     *     notes_ar: string,
     *     notes_en: string,
     * }
     */
    public function readManifestForForm(): array
    {
        $manifest = $this->readManifest();
        $release = $this->releaseService->latestRelease();

        return [
            'version' => (string) ($manifest['version'] ?? $release['version'] ?? config('mobile_app.version', '1.0.0')),
            'version_code' => (int) ($manifest['version_code'] ?? $release['version_code'] ?? config('mobile_app.version_code', 1)),
            'apk' => (string) ($manifest['apk'] ?? $release['apk_filename'] ?? ''),
            'force_update' => (bool) ($manifest['force_update'] ?? $release['force_update'] ?? false),
            'min_version_code' => isset($manifest['min_version_code']) && is_numeric($manifest['min_version_code'])
                ? (int) $manifest['min_version_code']
                : ($release['min_version_code'] ?? null),
            'notes_ar' => is_array($manifest['notes'] ?? null) ? (string) ($manifest['notes']['ar'] ?? '') : '',
            'notes_en' => is_array($manifest['notes'] ?? null)
                ? (string) ($manifest['notes']['en'] ?? '')
                : (string) ($manifest['notes'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentReleaseSummary(): ?array
    {
        $release = $this->releaseService->latestRelease();

        if ($release === null) {
            return null;
        }

        return [
            'version' => $release['version'],
            'version_code' => $release['version_code'],
            'apk_filename' => $release['apk_filename'],
            'download_url' => $release['download_url'],
            'page_url' => $release['page_url'],
            'force_update' => $release['force_update'],
            'min_version_code' => $release['min_version_code'],
            'notes' => $release['notes'],
            'published_at' => $release['published_at'],
            'sha256' => $release['sha256'],
            'file_size' => $release['file_size'],
        ];
    }

    /**
     * @return list<array{filename: string, version: string|null, version_code: int|null, size: int, updated_at: string|null}>
     */
    public function listApkFiles(): array
    {
        $directory = $this->releasesDirectory();

        if (! File::isDirectory($directory)) {
            return [];
        }

        $files = [];

        foreach (File::files($directory) as $file) {
            if (strtolower($file->getExtension()) !== 'apk') {
                continue;
            }

            [$version, $versionCode] = $this->parseVersionFromFilename($file->getFilename());

            $files[] = [
                'filename' => $file->getFilename(),
                'version' => $version,
                'version_code' => $versionCode,
                'size' => $file->getSize(),
                'updated_at' => date('c', $file->getMTime()),
            ];
        }

        usort($files, fn (array $left, array $right): int => ($right['version_code'] ?? 0) <=> ($left['version_code'] ?? 0));

        return $files;
    }

    public function uploadApk(UploadedFile $file, string $version, int $versionCode, bool $notifyUsers = true): array
    {
        File::ensureDirectoryExists($this->releasesDirectory());

        $filename = $this->buildApkFilename($version, $versionCode);
        $destination = $this->releasesDirectory().DIRECTORY_SEPARATOR.$filename;
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension === 'zip') {
            $this->extractApkFromZip($file, $destination);
        } else {
            $file->move($this->releasesDirectory(), $filename);
        }

        return [
            'filename' => $filename,
            'notify' => $this->finalizeUpload($version, $versionCode, $filename, notifyUsers: $notifyUsers),
        ];
    }

    private function extractApkFromZip(UploadedFile $file, string $destination): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages([
                'apk' => 'ZIP support is unavailable on this server (ZipArchive missing).',
            ]);
        }

        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages([
                'apk' => 'The uploaded ZIP could not be opened.',
            ]);
        }

        $apkEntries = [];

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entryName = (string) $zip->getNameIndex($index);

                if ($entryName === '' || str_ends_with($entryName, '/')) {
                    continue;
                }

                $normalized = str_replace('\\', '/', $entryName);

                if (str_contains($normalized, '../') || str_starts_with($normalized, '/')) {
                    continue;
                }

                if (! str_ends_with(strtolower($normalized), '.apk')) {
                    continue;
                }

                $stat = $zip->statIndex($index);
                $apkEntries[] = [
                    'name' => $entryName,
                    'size' => (int) ($stat['size'] ?? 0),
                    'basename' => strtolower(basename($normalized)),
                ];
            }

            if ($apkEntries === []) {
                throw ValidationException::withMessages([
                    'apk' => 'The ZIP must contain at least one .apk file.',
                ]);
            }

            usort($apkEntries, function (array $left, array $right): int {
                $preferred = ['app-release.apk', 'release.apk', 'app.apk'];

                $leftRank = array_search($left['basename'], $preferred, true);
                $rightRank = array_search($right['basename'], $preferred, true);
                $leftRank = $leftRank === false ? PHP_INT_MAX : $leftRank;
                $rightRank = $rightRank === false ? PHP_INT_MAX : $rightRank;

                if ($leftRank !== $rightRank) {
                    return $leftRank <=> $rightRank;
                }

                return $right['size'] <=> $left['size'];
            });

            $chosen = $apkEntries[0]['name'];
            $stream = $zip->getStream($chosen);

            if ($stream === false) {
                throw ValidationException::withMessages([
                    'apk' => 'Failed to read the APK inside the ZIP.',
                ]);
            }

            $target = fopen($destination, 'wb');

            if ($target === false) {
                fclose($stream);

                throw ValidationException::withMessages([
                    'apk' => 'Failed to write the extracted APK.',
                ]);
            }

            try {
                stream_copy_to_stream($stream, $target);
            } finally {
                fclose($stream);
                fclose($target);
            }
        } finally {
            $zip->close();
        }

        if (! File::isFile($destination) || File::size($destination) < 1) {
            File::delete($destination);

            throw ValidationException::withMessages([
                'apk' => 'The extracted APK is empty or missing.',
            ]);
        }
    }

    /**
     * @param  array{
     *     version: string,
     *     version_code: int,
     *     apk: string,
     *     force_update: bool,
     *     min_version_code: int|null,
     *     notes_ar: string,
     *     notes_en: string,
     * }|null  $existing
     */
    public function uploadApkFromPath(string $targetPath, string $version, int $versionCode, ?array $existing = null, bool $notifyUsers = false): ?array
    {
        return $this->finalizeUpload($version, $versionCode, basename($targetPath), $existing, $notifyUsers);
    }

    /**
     * @return array{
     *     max_upload_kb: int,
     *     php_upload_max_kb: int,
     *     php_post_max_kb: int,
     *     effective_max_kb: int,
     *     php_upload_max_label: string,
     *     php_post_max_label: string,
     *     php_ready: bool,
     * }
     */
    public function uploadLimits(): array
    {
        $maxUploadKb = max(1, (int) config('mobile_app.max_upload_kb', 512000));
        $phpUploadKb = PhpIniSize::toKilobytes((string) ini_get('upload_max_filesize'));
        $phpPostKb = PhpIniSize::toKilobytes((string) ini_get('post_max_size'));
        $effectiveKb = max(1, min($maxUploadKb, $phpUploadKb, $phpPostKb));

        return [
            'max_upload_kb' => $maxUploadKb,
            'php_upload_max_kb' => $phpUploadKb,
            'php_post_max_kb' => $phpPostKb,
            'effective_max_kb' => $effectiveKb,
            'php_upload_max_label' => (string) ini_get('upload_max_filesize'),
            'php_post_max_label' => (string) ini_get('post_max_size'),
            'php_ready' => $effectiveKb >= min($maxUploadKb, 131072),
        ];
    }

    public function buildApkFilename(string $version, int $versionCode): string
    {
        return sprintf('booke-%s+%d.apk', $version, $versionCode);
    }

    /**
     * @return array{recipients: int, delivered: int, failed: int, skipped_up_to_date: int}|null
     */
    public function notifyUsersOfRelease(): ?array
    {
        $release = $this->currentReleaseSummary();

        if ($release === null) {
            return null;
        }

        $notes = is_array($release['notes'] ?? null) ? $release['notes'] : [];

        $job = new BroadcastMobileAppReleaseNotificationsJob(
            version: (string) $release['version'],
            versionCode: (int) $release['version_code'],
            notesAr: (string) ($notes['ar'] ?? ''),
            notesEn: (string) ($notes['en'] ?? ''),
            downloadUrl: (string) $release['download_url'],
            pageUrl: (string) $release['page_url'],
            forceUpdate: (bool) $release['force_update'],
        );

        return $job->handle(
            app(NotificationService::class),
            app(NotificationTemplateSyncService::class),
        );
    }

    /**
     * @param  array{
     *     version: string,
     *     version_code: int,
     *     apk: string,
     *     force_update: bool,
     *     min_version_code: int|null,
     *     notes_ar: string,
     *     notes_en: string,
     * }|null  $existing
     * @return array{recipients: int, delivered: int, failed: int, skipped_up_to_date: int}|null
     */
    private function finalizeUpload(string $version, int $versionCode, string $filename, ?array $existing = null, bool $notifyUsers = true): ?array
    {
        $existing ??= $this->readManifestForForm();

        $this->writeManifest([
            'version' => $version,
            'version_code' => $versionCode,
            'apk' => $filename,
            'force_update' => $existing['force_update'],
            'min_version_code' => $existing['min_version_code'],
            'notes_ar' => $existing['notes_ar'],
            'notes_en' => $existing['notes_en'],
        ]);

        $this->releaseService->flushCache();

        if (! $notifyUsers) {
            return null;
        }

        return $this->notifyUsersOfRelease();
    }

    /**
     * @param  array{
     *     version: string,
     *     version_code: int,
     *     apk: string,
     *     force_update?: bool,
     *     min_version_code?: int|null,
     *     notes_ar?: string|null,
     *     notes_en?: string|null,
     * }  $data
     * @return array{recipients: int, delivered: int, failed: int, skipped_up_to_date: int}|null
     */
    public function updateReleaseSettings(array $data, bool $notifyUsers = false): ?array
    {
        $apk = basename((string) $data['apk']);
        $apkPath = $this->releasesDirectory().DIRECTORY_SEPARATOR.$apk;

        if (! File::isFile($apkPath)) {
            throw ValidationException::withMessages([
                'apk' => 'The selected APK file was not found in storage/app/releases.',
            ]);
        }

        $this->writeManifest([
            'version' => $data['version'],
            'version_code' => (int) $data['version_code'],
            'apk' => $apk,
            'force_update' => (bool) ($data['force_update'] ?? false),
            'min_version_code' => array_key_exists('min_version_code', $data) && $data['min_version_code'] !== null
                ? (int) $data['min_version_code']
                : null,
            'notes_ar' => $data['notes_ar'] ?? '',
            'notes_en' => $data['notes_en'] ?? '',
            'clear_min_version_code' => ! (array_key_exists('min_version_code', $data) && $data['min_version_code'] !== null),
        ]);

        $this->releaseService->flushCache();

        if (! $notifyUsers) {
            return null;
        }

        return $this->notifyUsersOfRelease();
    }

    /**
     * @param array{
     *     version: string,
     *     version_code: int,
     *     apk: string,
     *     force_update?: bool,
     *     min_version_code?: int|null,
     *     notes_ar?: string|null,
     *     notes_en?: string|null,
     *     clear_min_version_code?: bool,
     *     sha256?: string,
     * } $data
     */
    private function writeManifest(array $data): void
    {
        File::ensureDirectoryExists($this->releasesDirectory());

        $manifest = [
            'version' => $data['version'],
            'version_code' => $data['version_code'],
            'apk' => $data['apk'],
            'force_update' => (bool) ($data['force_update'] ?? false),
            'notes' => [
                'ar' => trim((string) ($data['notes_ar'] ?? '')),
                'en' => trim((string) ($data['notes_en'] ?? '')),
            ],
        ];

        $apkPath = $this->releasesDirectory().DIRECTORY_SEPARATOR.$data['apk'];

        if (array_key_exists('sha256', $data) && is_string($data['sha256']) && $data['sha256'] !== '') {
            $manifest['sha256'] = $data['sha256'];
        } elseif (File::isFile($apkPath)) {
            $manifest['sha256'] = hash_file('sha256', $apkPath) ?: null;
        }

        $shouldClearMin = (bool) ($data['clear_min_version_code'] ?? false);

        if (! $shouldClearMin && array_key_exists('min_version_code', $data) && $data['min_version_code'] !== null) {
            $manifest['min_version_code'] = (int) $data['min_version_code'];
        }

        File::put(
            $this->manifestPath(),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
        );
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
}
