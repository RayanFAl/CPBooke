<?php

namespace App\Services\AI;

use App\Modules\AI\Services\AiSettingsService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Intent parser for voice UI actions. Does not search, book, or pay.
 */
class VoiceActionService
{
    public function __construct(
        private readonly GeminiClient $client,
        private readonly VoiceActionResponseNormalizer $normalizer,
        private readonly AIResponseValidator $validator,
        private readonly AiSettingsService $aiSettings,
    ) {
    }

    /**
     * @param  array<string, mixed>  $actionContext
     * @return array{fallback: bool, data: array<string, mixed>, reason: string|null}
     */
    public function interpret(string $message, array $actionContext = []): array
    {
        $userPrompt = json_encode([
            'message' => $message,
            'action_context' => $actionContext,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            $result = $this->client->generateJson(
                $this->systemInstruction(),
                $userPrompt,
                ['responseSchema' => $this->responseSchema()],
            );
        } catch (GeminiException $exception) {
            Log::info('Gemini voice action fallback', [
                'reason' => $exception->reason,
            ]);

            return $this->fallback($exception->reason);
        } catch (Throwable $exception) {
            Log::info('Gemini voice action fallback', [
                'reason' => $exception->getMessage(),
            ]);

            return $this->fallback(GeminiClient::REASON_UNAVAILABLE);
        }

        $decoded = $this->validator->decodeJsonObject($result['text']);
        if ($decoded === null) {
            return $this->fallback(GeminiClient::REASON_INVALID_RESPONSE);
        }

        return [
            'fallback' => false,
            'reason' => null,
            'data' => $this->normalizer->normalize($decoded, $actionContext),
        ];
    }

    /**
     * Shape the client already knows how to ignore, so on-device Rules NLU can run.
     *
     * @return array{fallback: bool, data: array<string, mixed>, reason: string|null}
     */
    private function fallback(string $reason): array
    {
        return [
            'fallback' => true,
            'reason' => $reason,
            'data' => [
                'intent' => 'unknown',
                'product' => 'none',
                'slots' => new \stdClass(),
                'missingSlots' => [],
                'needsClarification' => false,
                'assistantMessage' => '',
                'recommendations' => [],
                'navigationAction' => null,
                'fallback' => true,
                'fallbackReason' => $reason,
                'source' => 'fallback',
                'confidence' => 0,
                'model' => $this->aiSettings->model(),
            ],
        ];
    }

    private function systemInstruction(): string
    {
        return <<<'PROMPT'
You are Booke AI, a travel-app intent parser. Convert the user utterance into JSON only. Never book, never pay, and never invent data.

Classify the utterance as exactly one intent:
- "new_search": a new or edited flight, hotel, insurance, or eSIM search that has not run yet.
- "result_action": an action on results already listed in action_context.displayed_results.
- "clarify": the request cannot be executed safely.

Rules:
- If intent is "result_action" and displayed_results is empty, return intent "clarify" and action.type "none".
- When intent is "clarify", action.type is always "none".
- "اختارلي الأفضل" means criteria "price". Do not invent target_index unless the user names a number.
- target_index and target_indices are 1-based and must match displayed_results.
- Compare requests use action.type "compare_results" and target_indices (example [2, 4]).
- Cancel uses action.type "cancel". Back uses "go_back". Opening the full results screen uses "open_results".
- Never return confirm_booking, pay, confirm, or payment.
- Keep relative dates in slots.raw_date. Do not resolve them.
- Extract only what the user said or what is already in active_slots.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        $nullableString = ['type' => 'STRING', 'nullable' => true];

        return [
            'type' => 'OBJECT',
            'properties' => [
                'intent' => ['type' => 'STRING'],
                'confidence' => ['type' => 'NUMBER'],
                'service_type' => $nullableString,
                'slots' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'origin' => $nullableString,
                        'destination' => $nullableString,
                        'raw_date' => $nullableString,
                        'passengers' => ['type' => 'INTEGER', 'nullable' => true],
                        'notes' => $nullableString,
                    ],
                ],
                'action' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'type' => ['type' => 'STRING'],
                        'target_index' => ['type' => 'INTEGER', 'nullable' => true],
                        'target_indices' => [
                            'type' => 'ARRAY',
                            'nullable' => true,
                            'items' => ['type' => 'INTEGER'],
                        ],
                        'criteria' => $nullableString,
                    ],
                ],
                'clarify_reason' => $nullableString,
            ],
        ];
    }
}
