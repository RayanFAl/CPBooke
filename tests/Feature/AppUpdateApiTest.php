<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AppUpdateApiTest extends TestCase
{
    private string $releasesDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->releasesDirectory = storage_path('app/releases-test-api-'.uniqid());
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

    public function test_update_api_reports_when_a_newer_apk_is_available(): void
    {
        File::put($this->releasesDirectory.'/booke-1.2.0+120.apk', 'fake-apk-binary');
        File::put($this->releasesDirectory.'/release.json', json_encode([
            'version' => '1.2.0',
            'version_code' => 120,
            'apk' => 'booke-1.2.0+120.apk',
            'notes' => [
                'ar' => 'إصلاحات وتحسينات',
                'en' => 'Fixes and improvements',
            ],
        ], JSON_THROW_ON_ERROR));

        $this->getJson('/api/v1/app/update?version_code=100&locale=ar')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.update_available', true)
            ->assertJsonPath('data.latest_version', '1.2.0')
            ->assertJsonPath('data.latest_version_code', 120)
            ->assertJsonPath('data.notes', 'إصلاحات وتحسينات');
    }

    public function test_update_api_reports_no_update_when_client_is_current(): void
    {
        File::put($this->releasesDirectory.'/booke-1.2.0+120.apk', 'fake-apk-binary');

        $this->getJson('/api/v1/app/update?version_code=120')
            ->assertOk()
            ->assertJsonPath('data.update_available', false)
            ->assertJsonPath('data.latest_version_code', 120);
    }
}
