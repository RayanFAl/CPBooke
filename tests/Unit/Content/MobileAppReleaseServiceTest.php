<?php

namespace Tests\Unit\Content;

use App\Modules\Content\Services\MobileAppReleaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MobileAppReleaseServiceTest extends TestCase
{
    private string $releasesDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->releasesDirectory = storage_path('app/releases-test-'.uniqid());
        File::ensureDirectoryExists($this->releasesDirectory);

        config()->set('mobile_app.releases_directory', $this->releasesDirectory);
        config()->set('mobile_app.apk_path', '');
        config()->set('mobile_app.cache_seconds', 0);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->releasesDirectory);

        parent::tearDown();
    }

    public function test_it_picks_the_highest_version_code_apk_automatically(): void
    {
        File::put($this->releasesDirectory.'/booke-1.0.0+100.apk', 'apk-v100');
        File::put($this->releasesDirectory.'/booke-1.1.0+110.apk', 'apk-v110');

        $release = app(MobileAppReleaseService::class)->latestRelease();

        $this->assertNotNull($release);
        $this->assertSame('1.1.0', $release['version']);
        $this->assertSame(110, $release['version_code']);
        $this->assertStringContainsString('booke-1.1.0+110.apk', $release['apk_path']);
    }

    public function test_it_merges_release_json_notes_and_force_update_rules(): void
    {
        File::put($this->releasesDirectory.'/booke-1.2.0+120.apk', 'apk-v120');
        File::put($this->releasesDirectory.'/release.json', json_encode([
            'version' => '1.2.0',
            'version_code' => 120,
            'apk' => 'booke-1.2.0+120.apk',
            'force_update' => true,
            'notes' => [
                'ar' => 'إصدار جديد متاح',
                'en' => 'New release available',
            ],
        ], JSON_THROW_ON_ERROR));

        $service = app(MobileAppReleaseService::class);
        $release = $service->latestRelease();
        $update = $service->checkUpdate(100, 'ar');

        $this->assertTrue($release['force_update']);
        $this->assertSame('إصدار جديد متاح', $update['notes']);
        $this->assertTrue($update['update_available']);
        $this->assertTrue($update['force_update']);
    }

    public function test_it_caches_latest_release_when_enabled(): void
    {
        config()->set('mobile_app.cache_seconds', 60);

        File::put($this->releasesDirectory.'/booke-1.0.0+100.apk', 'apk-v100');

        $service = app(MobileAppReleaseService::class);
        $service->latestRelease();

        File::delete($this->releasesDirectory.'/booke-1.0.0+100.apk');
        File::put($this->releasesDirectory.'/booke-2.0.0+200.apk', 'apk-v200');

        $cachedRelease = $service->latestRelease();

        $this->assertSame('1.0.0', $cachedRelease['version']);

        Cache::forget('mobile_app.latest_release');

        $freshRelease = $service->latestRelease();

        $this->assertSame('2.0.0', $freshRelease['version']);
    }
}
