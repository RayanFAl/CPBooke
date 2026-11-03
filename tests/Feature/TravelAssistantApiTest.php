<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TravelAssistantApiTest extends TestCase
{
    public function test_simple_message_hints_rules_nlu_without_calling_gemini(): void
    {
        Http::fake();

        $this->postJson('/api/v1/ai/travel-assistant', [
            'message' => 'طيران من طرابلس لدبي بكرة',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fallback', true)
            ->assertJsonPath('data.fallbackReason', 'prefer_rules_nlu')
            ->assertJsonPath('data.source', 'rules_hint');

        Http::assertNothingSent();
    }

    public function test_complex_message_uses_gemini_and_returns_validated_slots(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.gemini.api_key', 'test-key');
        config()->set('ai.gemini.model', 'gemini-2.5-flash-lite');
        config()->set('ai.timezone', 'Africa/Tripoli');

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
                                        'departureDate' => null,
                                        'returnDate' => null,
                                        'dateRange' => ['type' => 'next_week'],
                                        'adults' => null,
                                        'children' => 0,
                                        'infants' => 0,
                                        'cabinClass' => null,
                                        'nonStop' => true,
                                        'airline' => null,
                                        'departureTimeFrom' => null,
                                        'departureTimeTo' => null,
                                        'budget' => null,
                                        'currency' => 'LYD',
                                        'sortPreference' => 'cheapest',
                                        'missingSlots' => ['departureDate', 'adults'],
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
            'message' => 'نبي أرخص رحلة من طرابلس لإسطنبول الأسبوع الجاي ومباشرة لكن ما نبيش الصبح بدري',
            'forceGemini' => true,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent', 'searchFlight')
            ->assertJsonPath('data.product', 'flight')
            ->assertJsonPath('data.slots.origin', 'طرابلس')
            ->assertJsonPath('data.slots.destination', 'إسطنبول')
            ->assertJsonPath('data.slots.nonStop', true)
            ->assertJsonPath('data.slots.sortPreference', 'cheapest')
            ->assertJsonPath('data.needsClarification', true)
            ->assertJsonPath('data.fallback', false)
            ->assertJsonPath('data.source', 'gemini');

        Http::assertSentCount(1);
    }

    public function test_gemini_failure_returns_fallback_payload(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.gemini.api_key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Resource exhausted'],
            ], 429),
        ]);

        $this->postJson('/api/v1/ai/travel-assistant', [
            'message' => 'نبي أرخص رحلة الأسبوع الجاي لكن ما نبيش الصبح ولو الفرق أقل من 200 دينار نفضل المباشر',
            'forceGemini' => true,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fallback', true)
            ->assertJsonPath('data.intent', 'unknown');
    }

    public function test_missing_api_key_returns_fallback(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.gemini.api_key', '');

        Http::fake();

        $this->postJson('/api/v1/ai/travel-assistant', [
            'message' => 'نبي أرخص رحلة الأسبوع الجاي لكن ما نبيش الصبح',
            'forceGemini' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.fallback', true)
            ->assertJsonPath('data.fallbackReason', 'missing_api_key');

        Http::assertNothingSent();
    }

    public function test_recommendation_mode_never_invents_offer_ids(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.gemini.api_key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'recommendations' => [
                                            [
                                                'offerId' => 'real-1',
                                                'label' => 'cheapest',
                                                'reason' => 'أقل سعر',
                                            ],
                                            [
                                                'offerId' => 'hallucinated-99',
                                                'label' => 'fastest',
                                                'reason' => 'مختلق',
                                            ],
                                            [
                                                'offerId' => 'real-2',
                                                'label' => 'best_direct',
                                                'reason' => 'مباشرة',
                                            ],
                                        ],
                                    ], JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->postJson('/api/v1/ai/travel-assistant', [
            'mode' => 'recommend',
            'search' => [
                'origin' => 'TIP',
                'destination' => 'IST',
                'adults' => 2,
            ],
            'preferences' => [
                'priority' => 'cheapest',
                'nonStop' => true,
            ],
            'offers' => [
                [
                    'offerId' => 'real-1',
                    'airline' => 'Turkish Airlines',
                    'price' => 1620,
                    'currency' => 'LYD',
                    'stops' => 1,
                    'departure' => '08:00',
                    'arrival' => '14:00',
                    'duration' => '06:00',
                ],
                [
                    'offerId' => 'real-2',
                    'airline' => 'Afriqiyah',
                    'price' => 1850,
                    'currency' => 'LYD',
                    'stops' => 0,
                    'departure' => '10:20',
                    'arrival' => '14:30',
                    'duration' => '03:10',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.recommendations')
            ->assertJsonPath('data.recommendations.0.offerId', 'real-1')
            ->assertJsonPath('data.recommendations.1.offerId', 'real-2');
    }

    public function test_validation_requires_message_or_offers(): void
    {
        $this->postJson('/api/v1/ai/travel-assistant', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_voice_action_mode_is_accepted_and_returns_parser_json(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.gemini.api_key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'intent' => 'result_action',
                                        'confidence' => 0.91,
                                        'service_type' => 'flight',
                                        'slots' => [
                                            'origin' => null,
                                            'destination' => null,
                                            'raw_date' => null,
                                            'passengers' => null,
                                            'notes' => null,
                                        ],
                                        'action' => [
                                            'type' => 'select_result',
                                            'target_index' => 2,
                                            'target_indices' => null,
                                            'criteria' => 'price',
                                        ],
                                        'clarify_reason' => null,
                                    ], JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->postJson('/api/v1/ai/travel-assistant', [
            'mode' => 'voice_action',
            'message' => 'اختار الثانية الأرخص',
            'forceGemini' => true,
            'action_context' => [
                'current_screen' => 'flight_results',
                'service_type' => 'flight',
                'displayed_results' => [
                    ['index' => 1, 'id' => 'a', 'summary' => 'First'],
                    ['index' => 2, 'id' => 'b', 'summary' => 'Second'],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent', 'result_action')
            ->assertJsonPath('data.service_type', 'flight')
            ->assertJsonPath('data.action.type', 'select_result')
            ->assertJsonPath('data.action.target_index', 2)
            ->assertJsonPath('data.action.criteria', 'price');
    }

    public function test_voice_action_rejects_payment_and_falls_back_when_gemini_is_down(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.gemini.api_key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'intent' => 'result_action',
                                        'confidence' => 0.8,
                                        'service_type' => 'flight',
                                        'action' => ['type' => 'pay'],
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->postJson('/api/v1/ai/travel-assistant', [
            'mode' => 'voice_action',
            'message' => 'ادفع الحين',
        ])
            ->assertOk()
            ->assertJsonPath('data.intent', 'clarify')
            ->assertJsonPath('data.action.type', 'none');

        config()->set('ai.gemini.api_key', '');

        $this->postJson('/api/v1/ai/travel-assistant', [
            'mode' => 'voice_action',
            'message' => 'اختار الأرخص',
        ])
            ->assertOk()
            ->assertJsonPath('data.fallback', true)
            ->assertJsonPath('data.intent', 'unknown');
    }
}
