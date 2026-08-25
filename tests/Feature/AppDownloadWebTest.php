<?php

namespace Tests\Feature;

use App\Models\AppDownloadEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AppDownloadWebTest extends TestCase
{
    use RefreshDatabase;

    private string $releasesDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->releasesDirectory = storage_path('app/releases-test-web-'.uniqid());
        File::ensureDirectoryExists($this->releasesDirectory);

        config()->set('mobile_app.releases_directory', $this->releasesDirectory);
        config()->set('mobile_app.apk_path', '');
        config()->set('mobile_app.cache_seconds', 0);
        config()->set('mobile_app.name', 'Booke');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->releasesDirectory);

        parent::tearDown();
    }

    public function test_app_download_page_is_public(): void
    {
        File::put($this->releasesDirectory.'/booke-1.0.0+100.apk', 'fake-apk-binary');

        $this->get('/app?locale=ar')
            ->assertOk()
            ->assertSee('Booke', false)
            ->assertSee('حمّل تطبيق Booke', false)
            ->assertSee('1.0.0', false)
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertDontSee('login', false);
    }

    public function test_app_download_file_returns_not_found_when_apk_is_missing(): void
    {
        $this->get('/app/download')->assertNotFound();
    }

    public function test_app_download_file_serves_latest_detected_apk(): void
    {
        File::put($this->releasesDirectory.'/booke-1.0.0+100.apk', 'fake-apk-binary');
        File::put($this->releasesDirectory.'/booke-1.1.0+110.apk', 'fake-apk-binary-v2');

        $this->get('/app/download')
            ->assertOk()
            ->assertDownload('booke-1.1.0+110.apk');

        $this->assertDatabaseHas('app_download_events', [
            'event_type' => AppDownloadEvent::TYPE_APK_DOWNLOAD,
            'apk_filename' => 'booke-1.1.0+110.apk',
            'version' => '1.1.0',
        ]);
    }

    public function test_app_download_page_records_a_page_view(): void
    {
        File::put($this->releasesDirectory.'/booke-1.0.0+100.apk', 'fake-apk-binary');

        $this->get('/app?locale=ar')->assertOk();

        $this->assertDatabaseHas('app_download_events', [
            'event_type' => AppDownloadEvent::TYPE_PAGE_VIEW,
            'locale' => 'ar',
        ]);
    }
}
