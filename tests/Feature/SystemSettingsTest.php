<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_view_and_update_system_settings(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        $this->actingAs($actor)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/settings/pages/Index')
                ->has('settings')
                ->has('channelStatus')
                ->has('currencyOptions'));

        $this->actingAs($actor)
            ->put(route('admin.settings.update'), [
                'company_legal_name' => 'CPBooke LLC',
                'company_display_name' => 'CPBooke',
                'support_email' => 'support@cpbooke.test',
                'support_phone' => '+218910000000',
                'website_url' => 'https://cpbooke.test',
                'tax_id' => 'TAX-1',
                'company_address' => 'Tripoli',
                'default_currency' => 'USD',
                'timezone' => 'Africa/Tripoli',
                'default_locale' => 'en',
                'default_margin_percent' => 7.5,
                'email_enabled' => true,
                'sms_enabled' => false,
                'whatsapp_enabled' => false,
                'push_enabled' => true,
                'mail_from_name' => 'CPBooke',
                'sms_sender_id' => null,
                'maintenance_mode' => false,
                'support_chat_enabled' => true,
                'orders_legacy_create_enabled' => false,
                'home_offers_enabled' => true,
            ])
            ->assertRedirect(route('admin.settings.index'));

        $settings = SystemSetting::query()->firstOrFail();

        $this->assertSame('CPBooke LLC', $settings->company_legal_name);
        $this->assertSame('USD', $settings->default_currency);
        $this->assertSame('7.50', number_format((float) $settings->default_margin_percent, 2, '.', ''));
        $this->assertGreaterThanOrEqual(2, (int) $settings->settings_version);
    }

    public function test_settings_page_requires_settings_manage_permission(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPPORT_AGENT]);

        $this->actingAs($actor)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }
}
