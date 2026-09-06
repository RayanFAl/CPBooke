<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MobileAppAdminTest extends TestCase
{
    use RefreshDatabase;

    private string $releasesDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->releasesDirectory = storage_path('app/releases-admin-test-'.uniqid());
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

    public function test_mobile_app_page_requires_settings_manage_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_TEAM_MEMBER]);

        $this->actingAs($actor)
            ->get(route('admin.mobile-app.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_mobile_app_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = $this->superAdmin();

        $this->actingAs($actor)
            ->get(route('admin.mobile-app.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/mobile-app/pages/Index', false)
                ->has('manifest')
                ->has('apk_files')
                ->has('upload_limits')
            );
    }

    public function test_super_admin_can_upload_apk(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = $this->superAdmin();

        $this->actingAs($actor)
            ->post(route('admin.mobile-app.apk.upload'), [
                'version' => '1.2.0',
                'version_code' => 120,
                'apk' => UploadedFile::fake()->create('app-release.apk', 128, 'application/vnd.android.package-archive'),
            ])
            ->assertRedirect(route('admin.mobile-app.index'))
            ->assertSessionHas('success');

        $this->assertFileExists($this->releasesDirectory.DIRECTORY_SEPARATOR.'booke-1.2.0+120.apk');

        $manifest = json_decode((string) File::get($this->releasesDirectory.DIRECTORY_SEPARATOR.'release.json'), true);

        $this->assertSame('1.2.0', $manifest['version']);
        $this->assertSame(120, $manifest['version_code']);
        $this->assertSame('booke-1.2.0+120.apk', $manifest['apk']);

        $this->getJson('/api/v1/app/update?version_code=100&locale=en')
            ->assertOk()
            ->assertJsonPath('data.update_available', true);
    }

    public function test_super_admin_can_upload_zip_containing_apk(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = $this->superAdmin();

        $apkSource = $this->releasesDirectory.DIRECTORY_SEPARATOR.'nested-app.apk';
        $zipPath = $this->releasesDirectory.DIRECTORY_SEPARATOR.'release-bundle.zip';
        File::put($apkSource, 'zipped-apk-bytes');

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE) === true);
        $zip->addFile($apkSource, 'app-release.apk');
        $zip->close();

        $this->actingAs($actor)
            ->post(route('admin.mobile-app.apk.upload'), [
                'version' => '2.0.0',
                'version_code' => 200,
                'apk' => new UploadedFile($zipPath, 'release-bundle.zip', 'application/zip', null, true),
            ])
            ->assertRedirect(route('admin.mobile-app.index'))
            ->assertSessionHas('success');

        $destination = $this->releasesDirectory.DIRECTORY_SEPARATOR.'booke-2.0.0+200.apk';
        $this->assertFileExists($destination);
        $this->assertSame('zipped-apk-bytes', File::get($destination));

        $manifest = json_decode((string) File::get($this->releasesDirectory.DIRECTORY_SEPARATOR.'release.json'), true);
        $this->assertSame('booke-2.0.0+200.apk', $manifest['apk']);
    }

    public function test_zip_without_apk_is_rejected(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = $this->superAdmin();

        $zipPath = $this->releasesDirectory.DIRECTORY_SEPARATOR.'empty-bundle.zip';
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE) === true);
        $zip->addFromString('readme.txt', 'no apk here');
        $zip->close();

        $this->actingAs($actor)
            ->from(route('admin.mobile-app.index'))
            ->post(route('admin.mobile-app.apk.upload'), [
                'version' => '2.0.0',
                'version_code' => 200,
                'apk' => new UploadedFile($zipPath, 'empty-bundle.zip', 'application/zip', null, true),
            ])
            ->assertRedirect(route('admin.mobile-app.index'))
            ->assertSessionHasErrors('apk');

        $this->assertFileDoesNotExist($this->releasesDirectory.DIRECTORY_SEPARATOR.'booke-2.0.0+200.apk');
    }

    public function test_import_apk_command_uses_apk_version_option(): void
    {
        $source = $this->releasesDirectory.DIRECTORY_SEPARATOR.'source.apk';
        File::put($source, 'apk-bytes');

        $this->artisan('mobile-app:import-apk', [
            'path' => $source,
            '--apk-version' => '1.0.0',
            '--version-code' => 1,
        ])->assertSuccessful();

        $this->assertFileExists($this->releasesDirectory.DIRECTORY_SEPARATOR.'booke-1.0.0+1.apk');

        $manifest = json_decode((string) File::get($this->releasesDirectory.DIRECTORY_SEPARATOR.'release.json'), true);

        $this->assertSame('1.0.0', $manifest['version']);
        $this->assertSame(1, $manifest['version_code']);
        $this->assertSame('booke-1.0.0+1.apk', $manifest['apk']);
    }

    public function test_super_admin_can_update_release_settings_and_force_update(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = $this->superAdmin();
        $apkName = 'booke-1.2.0+120.apk';
        File::put($this->releasesDirectory.DIRECTORY_SEPARATOR.$apkName, 'apk-bytes');

        $this->actingAs($actor)
            ->put(route('admin.mobile-app.release.update'), [
                'version' => '1.2.0',
                'version_code' => 120,
                'apk' => $apkName,
                'force_update' => true,
                'min_version_code' => 110,
                'notes_ar' => 'تحديث إجباري',
                'notes_en' => 'Mandatory update',
            ])
            ->assertRedirect(route('admin.mobile-app.index'))
            ->assertSessionHas('success');

        $manifest = json_decode((string) File::get($this->releasesDirectory.DIRECTORY_SEPARATOR.'release.json'), true);

        $this->assertTrue($manifest['force_update']);
        $this->assertSame(110, $manifest['min_version_code']);
        $this->assertSame('تحديث إجباري', $manifest['notes']['ar']);
        $this->assertSame('Mandatory update', $manifest['notes']['en']);

        $this->getJson('/api/v1/app/update?version_code=100&locale=en')
            ->assertOk()
            ->assertJsonPath('data.update_available', true)
            ->assertJsonPath('data.force_update', true);
    }

    private function superAdmin(): User
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        return $actor;
    }
}
