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
            );
    }

    public function test_super_admin_can_upload_apk_and_update_release_settings(): void
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

        $this->actingAs($actor)
            ->put(route('admin.mobile-app.release.update'), [
                'version' => '1.2.0',
                'version_code' => 120,
                'apk' => 'booke-1.2.0+120.apk',
                'force_update' => true,
                'min_version_code' => 100,
                'notes_ar' => 'إصلاحات',
                'notes_en' => 'Fixes',
            ])
            ->assertRedirect(route('admin.mobile-app.index'))
            ->assertSessionHas('success');

        $manifest = json_decode((string) File::get($this->releasesDirectory.DIRECTORY_SEPARATOR.'release.json'), true);

        $this->assertSame('1.2.0', $manifest['version']);
        $this->assertSame(120, $manifest['version_code']);
        $this->assertTrue($manifest['force_update']);
        $this->assertSame('Fixes', $manifest['notes']['en']);

        $this->getJson('/api/v1/app/update?version_code=100&locale=en')
            ->assertOk()
            ->assertJsonPath('data.update_available', true)
            ->assertJsonPath('data.force_update', true)
            ->assertJsonPath('data.notes', 'Fixes');
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
