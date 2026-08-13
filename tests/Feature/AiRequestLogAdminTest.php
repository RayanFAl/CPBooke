<?php

namespace Tests\Feature;

use App\Models\AiTravelAssistantLog;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiRequestLogAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_travel_assistant_request_is_logged(): void
    {
        config()->set('ai.log_requests', true);
        config()->set('ai.gemini.api_key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'intent' => 'searchFlight',
                                        'product' => 'flight',
                                        'origin' => 'طرابلس',
                                        'destination' => 'إسطنبول',
                                        'missingSlots' => ['departureDate'],
                                        'needsClarification' => true,
                                        'confidence' => 0.9,
                                        'assistantMessage' => 'متى تحب السفر؟',
                                    ], JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->postJson('/api/v1/ai/travel-assistant', [
            'message' => 'نبي طيران من طرابلس لإسطنبول',
            'forceGemini' => true,
        ])->assertOk();

        $this->assertSame(1, AiTravelAssistantLog::query()->count());

        $log = AiTravelAssistantLog::query()->first();
        $this->assertSame('interpret', $log->mode);
        $this->assertSame('searchFlight', $log->intent);
        $this->assertSame('gemini', $log->source);
        $this->assertStringContainsString('طرابلس', (string) $log->message);
    }

    public function test_rules_hint_request_is_logged_with_rules_hint_mode(): void
    {
        config()->set('ai.log_requests', true);

        $this->postJson('/api/v1/ai/travel-assistant', [
            'message' => 'طيران من طرابلس لدبي بكرة',
        ])->assertOk();

        $log = AiTravelAssistantLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame('rules_hint', $log->mode);
        $this->assertSame('rules_hint', $log->source);
        $this->assertTrue($log->fallback);
    }

    public function test_admin_can_view_ai_request_logs_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        AiTravelAssistantLog::query()->create([
            'mode' => 'interpret',
            'message' => 'نبي طيران',
            'intent' => 'searchFlight',
            'product' => 'flight',
            'source' => 'gemini',
            'fallback' => false,
            'latency_ms' => 120,
            'success' => true,
        ]);

        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        $this->actingAs($actor)
            ->get(route('admin.ai.logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/ai/pages/RequestLogs', false)
                ->has('logs.data', 1)
                ->where('logs.data.0.message', 'نبي طيران'));
    }
}
