<?php

namespace App\Modules\Admin\MobileApp\Services;

use App\Modules\Content\Services\MobileAppReleaseService;
use App\Support\PhpIniSize;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

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

    public function uploadApk(UploadedFile $file, string $version, int $versionCode): string
    {
        File::ensureDirectoryExists($this->releasesDirectory());

        $filename = $this->buildApkFilename($version, $versionCode);
        $file->move($this->releasesDirectory(), $filename);

        $this->finalizeUpload($version, $versionCode, $filename);

        return $filename;
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
    public function uploadApkFromPath(string $targetPath, string $version, int $versionCode, ?array $existing = null): void
    {
        $this->finalizeUpload($version, $versionCode, basename($targetPath), $existing);
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
    private function finalizeUpload(string $version, int $versionCode, string $filename, ?array $existing = null): void
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

        if (array_key_exists('min_version_code', $data) && $data['min_version_code'] !== null) {
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
