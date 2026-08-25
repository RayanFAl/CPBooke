<?php

namespace App\Modules\Content\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class MobileAppReleaseService
{
    private const CACHE_KEY = 'mobile_app.latest_release';

    /**
     * @return array{
     *     version: string,
     *     version_code: int,
     *     apk_path: string,
     *     apk_filename: string,
     *     download_url: string,
     *     page_url: string,
     *     force_update: bool,
     *     min_version_code: int|null,
     *     notes: array{ar: string|null, en: string|null},
     *     published_at: string|null,
     *     sha256: string|null,
     *     file_size: int|null,
     * }|null
     */
    public function latestRelease(): ?array
    {
        $ttl = max(0, (int) config('mobile_app.cache_seconds', 60));

        if ($ttl === 0) {
            return $this->resolveLatestRelease();
        }

        return Cache::remember(self::CACHE_KEY, $ttl, fn (): ?array => $this->resolveLatestRelease());
    }

    /**
     * @return array{
     *     update_available: bool,
     *     latest_version: string|null,
     *     latest_version_code: int|null,
     *     download_url: string|null,
     *     page_url: string,
     *     force_update: bool,
     *     notes: string|null,
     *     published_at: string|null,
     *     sha256: string|null,
     *     file_size: int|null,
     * }
     */
    public function checkUpdate(int $currentVersionCode, ?string $locale = null): array
    {
        $release = $this->latestRelease();
        $pageUrl = route('app.download.page');

        if ($release === null) {
            return [
                'update_available' => false,
                'latest_version' => null,
                'latest_version_code' => null,
                'download_url' => null,
                'page_url' => $pageUrl,
                'force_update' => false,
                'notes' => null,
                'published_at' => null,
                'sha256' => null,
                'file_size' => null,
            ];
        }

        $notes = $this->resolveNotes($release['notes'], $locale);
        $forceUpdate = $release['force_update']
            || ($release['min_version_code'] !== null && $currentVersionCode < $release['min_version_code']);

        return [
            'update_available' => $currentVersionCode < $release['version_code'],
            'latest_version' => $release['version'],
            'latest_version_code' => $release['version_code'],
            'download_url' => $release['download_url'],
            'page_url' => $pageUrl,
            'force_update' => $forceUpdate,
            'notes' => $notes,
            'published_at' => $release['published_at'],
            'sha256' => $release['sha256'],
            'file_size' => $release['file_size'],
        ];
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *     version: string,
     *     version_code: int,
     *     apk_path: string,
     *     apk_filename: string,
     *     download_url: string,
     *     page_url: string,
     *     force_update: bool,
     *     min_version_code: int|null,
     *     notes: array{ar: string|null, en: string|null},
     *     published_at: string|null,
     *     sha256: string|null,
     *     file_size: int|null,
     * }|null
     */
    private function resolveLatestRelease(): ?array
    {
        $manifest = $this->readManifest();
        $candidates = $this->discoverApkCandidates();

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $left, array $right): int {
            if ($left['version_code'] !== $right['version_code']) {
                return $right['version_code'] <=> $left['version_code'];
            }

            return version_compare($right['version'], $left['version']);
        });

        $selected = $candidates[0];

        if (is_array($manifest)) {
            $selected = $this->applyManifest($selected, $manifest, $candidates);
        }

        if (! File::isFile($selected['apk_path'])) {
            return null;
        }

        return $this->formatRelease($selected, $manifest);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifest(): ?array
    {
        $manifestPath = $this->manifestPath();

        if (! File::isFile($manifestPath)) {
            return null;
        }

        $decoded = json_decode((string) File::get($manifestPath), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return list<array{apk_path: string, apk_filename: string, version: string, version_code: int}>
     */
    private function discoverApkCandidates(): array
    {
        $candidates = [];
        $configuredPath = (string) config('mobile_app.apk_path');

        if ($configuredPath !== '' && File::isFile($configuredPath)) {
            $candidates[] = $this->candidateFromPath($configuredPath);
        }

        $directory = $this->releasesDirectory();

        if (! File::isDirectory($directory)) {
            return $this->uniqueCandidates($candidates);
        }

        foreach (File::files($directory) as $file) {
            if (strtolower($file->getExtension()) !== 'apk') {
                continue;
            }

            $candidates[] = $this->candidateFromPath($file->getPathname());
        }

        return $this->uniqueCandidates($candidates);
    }

    /**
     * @param  list<array{apk_path: string, apk_filename: string, version: string, version_code: int}>  $candidates
     * @return list<array{apk_path: string, apk_filename: string, version: string, version_code: int}>
     */
    private function uniqueCandidates(array $candidates): array
    {
        $unique = [];

        foreach ($candidates as $candidate) {
            $unique[$candidate['apk_path']] = $candidate;
        }

        return array_values($unique);
    }

    /**
     * @return array{apk_path: string, apk_filename: string, version: string, version_code: int}
     */
    private function candidateFromPath(string $path): array
    {
        $filename = basename($path);
        [$version, $versionCode] = $this->parseVersionFromFilename($filename);

        if ($version === null) {
            $version = (string) config('mobile_app.version', '1.0.0');
        }

        if ($versionCode === null) {
            $versionCode = (int) config('mobile_app.version_code', 1);
        }

        return [
            'apk_path' => $path,
            'apk_filename' => $filename,
            'version' => $version,
            'version_code' => $versionCode,
        ];
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function parseVersionFromFilename(string $filename): array
    {
        if (preg_match('/(?:^|[._-])v?(\d+\.\d+\.\d+)[+_\-](\d+)\.apk$/i', $filename, $matches) === 1) {
            return [$matches[1], (int) $matches[2]];
        }

        if (preg_match('/(?:^|[._-])v?(\d+\.\d+\.\d+)\.apk$/i', $filename, $matches) === 1) {
            return [$matches[1], $this->versionCodeFromSemver($matches[1])];
        }

        return [null, null];
    }

    private function versionCodeFromSemver(string $version): int
    {
        $parts = array_map(intval(...), explode('.', $version));

        return ($parts[0] * 10000) + (($parts[1] ?? 0) * 100) + ($parts[2] ?? 0);
    }

    /**
     * @param  array{apk_path: string, apk_filename: string, version: string, version_code: int}  $selected
     * @param  array<string, mixed>  $manifest
     * @param  list<array{apk_path: string, apk_filename: string, version: string, version_code: int}>  $candidates
     * @return array{apk_path: string, apk_filename: string, version: string, version_code: int}
     */
    private function applyManifest(array $selected, array $manifest, array $candidates): array
    {
        $manifestApk = trim((string) ($manifest['apk'] ?? ''));

        if ($manifestApk !== '') {
            $manifestPath = $this->releasesDirectory().DIRECTORY_SEPARATOR.$manifestApk;

            if (File::isFile($manifestPath)) {
                $selected = $this->candidateFromPath($manifestPath);
            }
        }

        if (isset($manifest['version']) && is_string($manifest['version']) && $manifest['version'] !== '') {
            $selected['version'] = $manifest['version'];
        }

        if (isset($manifest['version_code']) && is_numeric($manifest['version_code'])) {
            $selected['version_code'] = (int) $manifest['version_code'];
        }

        if ($manifestApk === '' && isset($manifest['version_code']) && is_numeric($manifest['version_code'])) {
            foreach ($candidates as $candidate) {
                if ($candidate['version_code'] === (int) $manifest['version_code']) {
                    $selected = $candidate;
                    break;
                }
            }
        }

        return $selected;
    }

    /**
     * @param  array{apk_path: string, apk_filename: string, version: string, version_code: int}  $selected
     * @param  array<string, mixed>|null  $manifest
     * @return array{
     *     version: string,
     *     version_code: int,
     *     apk_path: string,
     *     apk_filename: string,
     *     download_url: string,
     *     page_url: string,
     *     force_update: bool,
     *     min_version_code: int|null,
     *     notes: array{ar: string|null, en: string|null},
     *     published_at: string|null,
     *     sha256: string|null,
     *     file_size: int|null,
     * }
     */
    private function formatRelease(array $selected, ?array $manifest): array
    {
        $notes = [
            'ar' => null,
            'en' => null,
        ];

        if (is_array($manifest)) {
            if (isset($manifest['notes']) && is_array($manifest['notes'])) {
                $notes['ar'] = isset($manifest['notes']['ar']) ? (string) $manifest['notes']['ar'] : null;
                $notes['en'] = isset($manifest['notes']['en']) ? (string) $manifest['notes']['en'] : null;
            } elseif (isset($manifest['notes']) && is_string($manifest['notes']) && $manifest['notes'] !== '') {
                $notes['en'] = $manifest['notes'];
            }
        }

        $forceUpdate = is_array($manifest) && (bool) ($manifest['force_update'] ?? false);
        $minVersionCode = is_array($manifest) && isset($manifest['min_version_code']) && is_numeric($manifest['min_version_code'])
            ? (int) $manifest['min_version_code']
            : null;

        $publishedAt = null;
        $fileSize = null;
        $sha256 = null;

        if (File::isFile($selected['apk_path'])) {
            $publishedAt = date('c', (int) File::lastModified($selected['apk_path']));
            $fileSize = File::size($selected['apk_path']);
            $sha256 = hash_file('sha256', $selected['apk_path']) ?: null;
        }

        return [
            'version' => $selected['version'],
            'version_code' => $selected['version_code'],
            'apk_path' => $selected['apk_path'],
            'apk_filename' => $selected['apk_filename'],
            'download_url' => route('app.download.file'),
            'page_url' => route('app.download.page'),
            'force_update' => $forceUpdate,
            'min_version_code' => $minVersionCode,
            'notes' => $notes,
            'published_at' => $publishedAt,
            'sha256' => $sha256,
            'file_size' => $fileSize,
        ];
    }

    /**
     * @param  array{ar: string|null, en: string|null}  $notes
     */
    private function resolveNotes(array $notes, ?string $locale): ?string
    {
        $locale = $locale === 'ar' ? 'ar' : 'en';

        return $notes[$locale] ?? $notes['en'] ?? $notes['ar'];
    }

    private function releasesDirectory(): string
    {
        $directory = (string) config('mobile_app.releases_directory');

        if ($directory === '') {
            throw new InvalidArgumentException('Mobile app releases directory is not configured.');
        }

        return $directory;
    }

    private function manifestPath(): string
    {
        return $this->releasesDirectory().DIRECTORY_SEPARATOR.'release.json';
    }
}
