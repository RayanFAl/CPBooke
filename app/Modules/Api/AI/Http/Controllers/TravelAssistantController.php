<?php

namespace App\Modules\Api\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Services\AiSettingsService;
use App\Modules\AI\Services\AiTravelAssistantLogService;
use App\Modules\Api\AI\Http\Requests\TravelAssistantRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Services\AI\GeminiRecommendationService;
use App\Services\AI\GeminiTravelAssistantService;
use App\Services\AI\VoiceActionService;
use Illuminate\Http\JsonResponse;
use Throwable;

class TravelAssistantController extends Controller
{
    public function __construct(
        private readonly GeminiTravelAssistantService $travelAssistant,
        private readonly GeminiRecommendationService $recommendationService,
        private readonly VoiceActionService $voiceActionService,
        private readonly AiSettingsService $aiSettings,
        private readonly AiTravelAssistantLogService $logService,
    ) {
    }

    public function __invoke(TravelAssistantRequest $request): JsonResponse
    {
        $started = hrtime(true);
        $validated = $request->validated();
        $mode = $validated['mode'] ?? null;
        $offers = $validated['offers'] ?? [];
        $message = trim((string) ($validated['message'] ?? ''));

        $isRecommend = $mode === 'recommend'
            || ($mode === null && is_array($offers) && $offers !== [] && $message === '');
        $isVoiceAction = $mode === 'voice_action';

        try {
            if ($isVoiceAction) {
                return $this->voiceAction($request, $validated, $started);
            }

            if ($isRecommend) {
                return $this->recommend($request, $validated, $started);
            }

            return $this->interpret($request, $validated, $started);
        } catch (Throwable $exception) {
            $this->logService->record(
                $request,
                $isVoiceAction ? 'voice_action' : ($isRecommend ? 'recommend' : 'interpret'),
                [
                    'message' => $message,
                    'model' => $this->aiSettings->model(),
                ],
                $this->elapsedMs($started),
                success: false,
                errorMessage: $exception->getMessage(),
            );

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function interpret(TravelAssistantRequest $request, array $validated, int $started): JsonResponse
    {
        $message = trim((string) ($validated['message'] ?? ''));
        $conversation = is_array($validated['conversation'] ?? null)
            ? $validated['conversation']
            : [];
        $searchContext = is_array($validated['searchContext'] ?? null)
            ? $validated['searchContext']
            : null;
        $forceGemini = (bool) ($validated['forceGemini'] ?? false);

        if (! $forceGemini
            && $this->aiSettings->preferRulesNlu()
            && $this->travelAssistant->shouldPreferRulesNlu($message)) {
            $data = [
                'message' => $message,
                'intent' => 'unknown',
                'product' => 'none',
                'slots' => new \stdClass(),
                'missingSlots' => [],
                'needsClarification' => false,
                'assistantMessage' => '',
                'recommendations' => [],
                'navigationAction' => null,
                'fallback' => true,
                'fallbackReason' => 'prefer_rules_nlu',
                'source' => 'rules_hint',
                'confidence' => 0,
                'model' => $this->aiSettings->model(),
            ];

            $this->logService->record(
                $request,
                'rules_hint',
                $data,
                $this->elapsedMs($started),
            );

            return ApiResponse::success(
                collect($data)->except(['message', 'model'])->all(),
                'Use on-device Rules NLU for this simple request.',
            );
        }

        $result = $this->travelAssistant->interpret($message, $conversation, $searchContext);
        $shaped = $this->shapeResponse($result);

        $this->logService->record(
            $request,
            'interpret',
            array_merge($shaped, [
                'message' => $message,
                'model' => $this->aiSettings->model(),
            ]),
            $this->elapsedMs($started),
            success: ! ($result['fallback'] ?? false),
            errorMessage: ($result['fallback'] ?? false)
                ? ($result['fallbackReason'] ?? 'fallback')
                : null,
        );

        return ApiResponse::success(
            $shaped,
            $result['fallback'] ?? false
                ? 'AI unavailable; client should use Rules NLU fallback.'
                : 'Travel intent extracted.',
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function voiceAction(TravelAssistantRequest $request, array $validated, int $started): JsonResponse
    {
        $message = trim((string) ($validated['message'] ?? ''));
        $actionContext = is_array($validated['action_context'] ?? null)
            ? $validated['action_context']
            : [];

        $result = $this->voiceActionService->interpret($message, $actionContext);
        $data = $result['data'];

        $this->logService->record(
            $request,
            'voice_action',
            array_merge($data, [
                'message' => $message,
                'model' => $this->aiSettings->model(),
                'product' => $data['service_type'] ?? ($data['product'] ?? null),
            ]),
            $this->elapsedMs($started),
            success: ! $result['fallback'],
            errorMessage: $result['reason'],
        );

        return ApiResponse::success(
            $data,
            $result['fallback']
                ? 'AI unavailable; client should use Rules NLU fallback.'
                : 'Voice action parsed.',
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function recommend(TravelAssistantRequest $request, array $validated, int $started): JsonResponse
    {
        $search = is_array($validated['search'] ?? null) ? $validated['search'] : [];
        $preferences = is_array($validated['preferences'] ?? null) ? $validated['preferences'] : [];
        $offers = is_array($validated['offers'] ?? null) ? $validated['offers'] : [];

        $recommendations = $this->recommendationService->recommend($search, $preferences, $offers);

        $data = [
            'intent' => 'searchFlight',
            'product' => 'flight',
            'slots' => new \stdClass(),
            'missingSlots' => [],
            'needsClarification' => false,
            'assistantMessage' => '',
            'recommendations' => $recommendations,
            'navigationAction' => null,
            'fallback' => false,
            'source' => 'gemini_or_local',
            'offers_count' => count($offers),
            'model' => $this->aiSettings->model(),
        ];

        $this->logService->record(
            $request,
            'recommend',
            $data,
            $this->elapsedMs($started),
        );

        return ApiResponse::success($data, 'Recommendations ready.');
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function shapeResponse(array $result): array
    {
        $slots = $result['slots'] ?? [];

        return [
            'intent' => $result['intent'] ?? 'unknown',
            'product' => $result['product'] ?? 'none',
            'slots' => $slots === [] ? new \stdClass() : $slots,
            'missingSlots' => $result['missingSlots'] ?? [],
            'needsClarification' => (bool) ($result['needsClarification'] ?? false),
            'assistantMessage' => (string) ($result['assistantMessage'] ?? ''),
            'recommendations' => $result['recommendations'] ?? [],
            'navigationAction' => $result['navigationAction'] ?? null,
            'confidence' => $result['confidence'] ?? 0,
            'fallback' => (bool) ($result['fallback'] ?? false),
            'fallbackReason' => $result['fallbackReason'] ?? null,
            'source' => $result['source'] ?? 'gemini',
            'currentDate' => $result['currentDate'] ?? null,
            'timezone' => $result['timezone'] ?? null,
        ];
    }

    private function elapsedMs(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
