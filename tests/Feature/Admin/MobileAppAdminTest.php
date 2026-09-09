<?php

namespace Tests\Feature\Admin;

use App\Models\NotificationLog;
use App\Models\User;
use App\Models\UserNotificationDevice;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
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

    public function test_upload_can_skip_user_notifications(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_active' => true,
        ]);
        UserNotificationDevice::query()->create([
            'user_id' => $customer->id,
            'channel' => NotificationChannels::PUSH,
            'platform' => 'android',
            'app_version' => '1.0.0',
            'app_version_code' => 100,
            'device_token' => str_repeat('a', 40),
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $actor = $this->superAdmin();

        $this->actingAs($actor)
            ->post(route('admin.mobile-app.apk.upload'), [
                'version' => '1.3.0',
                'version_code' => 130,
                'notify_users' => false,
                'apk' => UploadedFile::fake()->create('app-release.apk', 128, 'application/vnd.android.package-archive'),
            ])
            ->assertRedirect(route('admin.mobile-app.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('notification_logs', [
            'template_code' => 'APP_UPDATE_AVAILABLE',
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'template_code' => 'APP_UPDATE_AVAILABLE',
        ]);
    }

    public function test_upload_notifies_outdated_devices_and_skips_up_to_date(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $outdated = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_active' => true,
        ]);
        UserNotificationDevice::query()->create([
            'user_id' => $outdated->id,
            'channel' => NotificationChannels::PUSH,
            'platform' => 'android',
            'app_version' => '1.0.0',
            'app_version_code' => 100,
            'device_token' => str_repeat('b', 40),
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $current = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_active' => true,
        ]);
        UserNotificationDevice::query()->create([
            'user_id' => $current->id,
            'channel' => NotificationChannels::PUSH,
            'platform' => 'android',
            'app_version' => '1.2.0',
            'app_version_code' => 120,
            'device_token' => str_repeat('c', 40),
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $actor = $this->superAdmin();

        $this->actingAs($actor)
            ->post(route('admin.mobile-app.apk.upload'), [
                'version' => '1.2.0',
                'version_code' => 120,
                'notify_users' => true,
                'apk' => UploadedFile::fake()->create('app-release.apk', 128, 'application/vnd.android.package-archive'),
            ])
            ->assertRedirect(route('admin.mobile-app.index'))
            ->assertSessionHas('success', function (string $message): bool {
                return str_contains($message, 'Notifications:')
                    && str_contains($message, '1 recipients')
                    && str_contains($message, 'already up to date: 1');
            });

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $outdated->id,
            'template_code' => 'APP_UPDATE_AVAILABLE',
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $current->id,
            'template_code' => 'APP_UPDATE_AVAILABLE',
        ]);
        $this->assertSame(1, NotificationLog::query()
            ->where('template_code', 'APP_UPDATE_AVAILABLE')
            ->where('channel', NotificationChannels::PUSH)
            ->count());
    }

    public function test_device_registration_stores_app_version_code(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/devices', [
            'device_token' => str_repeat('d', 40),
            'platform' => 'android',
            'app_version' => '1.2.0',
            'app_version_code' => 120,
        ])
            ->assertCreated()
            ->assertJsonPath('data.app_version', '1.2.0')
            ->assertJsonPath('data.app_version_code', 120);

        $this->assertDatabaseHas('user_notification_devices', [
            'user_id' => $user->id,
            'app_version' => '1.2.0',
            'app_version_code' => 120,
        ]);
    }

    public function test_release_settings_can_send_update_notifications(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_active' => true,
        ]);
        UserNotificationDevice::query()->create([
            'user_id' => $customer->id,
            'channel' => NotificationChannels::PUSH,
            'platform' => 'android',
            'app_version_code' => 100,
            'device_token' => str_repeat('e', 40),
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $actor = $this->superAdmin();
        $apkName = 'booke-1.2.0+120.apk';
        File::put($this->releasesDirectory.DIRECTORY_SEPARATOR.$apkName, 'apk-bytes');

        $this->actingAs($actor)
            ->put(route('admin.mobile-app.release.update'), [
                'version' => '1.2.0',
                'version_code' => 120,
                'apk' => $apkName,
                'force_update' => true,
                'notify_users' => true,
                'min_version_code' => 110,
                'notes_ar' => 'تحديث إجباري',
                'notes_en' => 'Mandatory update',
            ])
            ->assertRedirect(route('admin.mobile-app.index'))
            ->assertSessionHas('success', function (string $message): bool {
                return str_contains($message, 'Notifications:')
                    && str_contains($message, '1 recipients');
            });

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $customer->id,
            'template_code' => 'APP_UPDATE_AVAILABLE',
        ]);
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
                'notify_users' => false,
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
        $this->assertDatabaseMissing('notification_logs', [
            'template_code' => 'APP_UPDATE_AVAILABLE',
        ]);
    }

    public function test_import_apk_command_can_notify_users(): void
    {
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_active' => true,
        ]);
        UserNotificationDevice::query()->create([
            'user_id' => $customer->id,
            'channel' => NotificationChannels::PUSH,
            'platform' => 'android',
            'app_version_code' => 1,
            'device_token' => str_repeat('f', 40),
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $source = $this->releasesDirectory.DIRECTORY_SEPARATOR.'source.apk';
        File::put($source, 'apk-bytes');

        $this->artisan('mobile-app:import-apk', [
            'path' => $source,
            '--apk-version' => '1.1.0',
            '--version-code' => 11,
            '--notify' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Notifications: 1 recipients');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $customer->id,
            'template_code' => 'APP_UPDATE_AVAILABLE',
        ]);
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
