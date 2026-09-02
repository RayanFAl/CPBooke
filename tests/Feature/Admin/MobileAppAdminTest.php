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
