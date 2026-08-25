<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_settings_page_requires_settings_manage_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_TEAM_MEMBER]);

        $this->actingAs($actor)
            ->get(route('admin.ai.settings.index'))
            ->assertForbidden();
    }

    public function test_admin_can_update_ai_settings_in_metadata(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        SystemSetting::query()->create(SystemSetting::defaultAttributes());

        $this->actingAs($actor)
            ->put(route('admin.ai.settings.update'), [
                'enabled' => true,
                'provider' => 'gemini',
                'model' => 'gemini-2.5-flash-lite',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'timeout' => 15,
                'max_output_tokens' => 512,
                'temperature' => 0.1,
                'max_offers_for_recommendation' => 5,
                'max_conversation_turns' => 4,
                'timezone' => 'Africa/Tripoli',
                'default_currency' => 'LYD',
                'prefer_rules_nlu' => true,
            ])
            ->assertRedirect(route('admin.ai.settings.index'));

        $settings = SystemSetting::query()->first();
        $this->assertSame('gemini-2.5-flash-lite', data_get($settings->metadata, 'ai.model'));
        $this->assertSame(15, data_get($settings->metadata, 'ai.timeout'));
        $this->assertTrue(data_get($settings->metadata, 'ai.prefer_rules_nlu'));
    }

    public function test_only_super_admin_can_toggle_ai_enabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_ADMIN]);

        SystemSetting::query()->create(SystemSetting::defaultAttributes());

        $payload = [
            'enabled' => false,
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash-lite',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'timeout' => 12,
            'max_output_tokens' => 1024,
            'temperature' => 0.2,
            'max_offers_for_recommendation' => 8,
            'max_conversation_turns' => 6,
            'timezone' => 'Africa/Tripoli',
            'default_currency' => 'LYD',
            'prefer_rules_nlu' => true,
        ];

        $this->actingAs($actor)
            ->put(route('admin.ai.settings.update'), $payload)
            ->assertForbidden();
    }

    public function test_test_connection_reports_missing_api_key(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config()->set('ai.gemini.api_key', '');

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        SystemSetting::query()->create(SystemSetting::defaultAttributes());

        $this->actingAs($actor)
            ->postJson(route('admin.ai.settings.test'))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'missing_api_key');
    }

    public function test_test_connection_can_succeed_with_fake_gemini(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config()->set('ai.gemini.api_key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"ok":true}'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        SystemSetting::query()->create(SystemSetting::defaultAttributes());

        $this->actingAs($actor)
            ->postJson(route('admin.ai.settings.test'))
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
