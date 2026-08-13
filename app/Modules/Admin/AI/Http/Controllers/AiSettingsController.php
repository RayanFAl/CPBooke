<?php

namespace App\Modules\Admin\AI\Http\Controllers;

use App\Modules\Admin\AI\Http\Requests\UpdateAiSettingsRequest;
use App\Modules\Admin\AI\Services\AiSettingsAdminService;
use App\Modules\AI\Services\AiSettingsService;
use App\Services\AI\GeminiClient;
use App\Services\AI\GeminiException;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController
{
    public function __construct(
        private readonly AiSettingsAdminService $adminService,
        private readonly AiSettingsService $aiSettings,
        private readonly GeminiClient $geminiClient,
    ) {
    }

    public function index(Request $request): Response
    {
        $payload = $this->adminService->getAdminPayload();

        return Inertia::render('admin/ai/pages/Index', [
            'settings' => $payload['settings'],
            'integration' => $payload['integration'],
            'models' => $payload['models'],
            'can_toggle_enabled' => $request->user()?->hasRole(RbacRegistry::ROLE_SUPER_ADMIN) ?? false,
            'update_url' => route('admin.ai.settings.update', absolute: false),
            'test_url' => route('admin.ai.settings.test', absolute: false),
        ]);
    }

    public function update(UpdateAiSettingsRequest $request): RedirectResponse
    {
        $this->adminService->update($request->user(), $request->validated());

        return redirect()
            ->route('admin.ai.settings.index')
            ->with('success', 'AI settings saved successfully.');
    }

    public function testConnection(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('settings.manage'), 403);

        if (! $this->aiSettings->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'GEMINI_API_KEY is not configured in .env',
                'reason' => GeminiClient::REASON_MISSING_KEY,
            ], 422);
        }

        if (! $this->aiSettings->enabled()) {
            return response()->json([
                'success' => false,
                'message' => 'AI travel assistant is disabled in admin settings.',
                'reason' => GeminiClient::REASON_DISABLED,
            ], 422);
        }

        try {
            $result = $this->geminiClient->generateJson(
                'Reply with JSON only: {"ok":true}',
                'Health check ping',
                [
                    'responseSchema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'ok' => ['type' => 'BOOLEAN'],
                        ],
                        'required' => ['ok'],
                    ],
                    'maxOutputTokens' => 32,
                ],
            );

            return response()->json([
                'success' => true,
                'message' => 'Gemini connection successful.',
                'model' => $this->aiSettings->model(),
                'sample' => mb_substr($result['text'], 0, 120),
            ]);
        } catch (GeminiException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'reason' => $exception->reason,
            ], 422);
        }
    }
}
