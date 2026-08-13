<?php

namespace Tests\Unit;

use App\Services\AI\AIResponseValidator;
use App\Services\AI\GeminiClient;
use App\Services\AI\GeminiTravelAssistantService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AIResponseValidatorTest extends TestCase
{
    public function test_resolves_tomorrow_from_current_date(): void
    {
        $validator = new AIResponseValidator();

        $result = $validator->validateIntent([
            'intent' => 'searchFlight',
            'product' => 'flight',
            'origin' => 'طرابلس',
            'destination' => 'دبي',
            'departureDate' => 'tomorrow',
            'adults' => 2,
            'missingSlots' => [],
            'needsClarification' => false,
            'confidence' => 0.95,
            'assistantMessage' => '',
        ], null, Carbon::parse('2026-08-11', 'Africa/Tripoli'));

        $this->assertSame('2026-08-12', $result['slots']['departureDate']);
        $this->assertSame(2, $result['slots']['adults']);
        $this->assertFalse($result['needsClarification']);
        $this->assertSame([], $result['missingSlots']);
    }

    public function test_merges_search_context_for_preference_updates(): void
    {
        $validator = new AIResponseValidator();

        $result = $validator->validateIntent([
            'intent' => 'searchFlight',
            'product' => 'flight',
            'cabinClass' => 'business',
            'departureTimeFrom' => '08:00',
            'missingSlots' => [],
            'needsClarification' => false,
            'confidence' => 0.8,
            'assistantMessage' => '',
        ], [
            'product' => 'flight',
            'origin' => 'طرابلس',
            'destination' => 'إسطنبول',
            'departureDate' => '2026-08-18',
            'adults' => 1,
        ], Carbon::parse('2026-08-11'));

        $this->assertSame('طرابلس', $result['slots']['origin']);
        $this->assertSame('إسطنبول', $result['slots']['destination']);
        $this->assertSame('2026-08-18', $result['slots']['departureDate']);
        $this->assertSame('business', $result['slots']['cabinClass']);
        $this->assertSame('08:00', $result['slots']['departureTimeFrom']);
        $this->assertFalse($result['needsClarification']);
    }

    public function test_rejects_hallucinated_recommendation_offer_ids(): void
    {
        $validator = new AIResponseValidator();

        $result = $validator->validateRecommendations([
            'recommendations' => [
                ['offerId' => 'a', 'label' => 'cheapest', 'reason' => 'ok'],
                ['offerId' => 'fake', 'label' => 'fastest', 'reason' => 'no'],
            ],
        ], ['a', 'b']);

        $this->assertCount(1, $result);
        $this->assertSame('a', $result[0]['offerId']);
    }

    public function test_hotel_requires_check_in_and_check_out(): void
    {
        $validator = new AIResponseValidator();

        $result = $validator->validateIntent([
            'intent' => 'searchHotel',
            'product' => 'hotel',
            'hotelCity' => 'طرابلس',
            'adults' => 2,
            'missingSlots' => [],
            'needsClarification' => false,
            'confidence' => 0.9,
            'assistantMessage' => '',
        ], null, Carbon::parse('2026-08-11', 'Africa/Tripoli'));

        $this->assertSame('hotel', $result['product']);
        $this->assertSame('طرابلس', $result['slots']['hotelCity']);
        $this->assertTrue($result['needsClarification']);
        $this->assertContains('checkIn', $result['missingSlots']);
        $this->assertContains('checkOut', $result['missingSlots']);
    }

    public function test_insurance_resolves_start_date(): void
    {
        $validator = new AIResponseValidator();

        $result = $validator->validateIntent([
            'intent' => 'searchInsurance',
            'product' => 'insurance',
            'zoneName' => 'أوروبا',
            'insuranceStart' => 'tomorrow',
            'insuranceEnd' => '2026-09-01',
            'adults' => 1,
            'missingSlots' => [],
            'needsClarification' => false,
            'confidence' => 0.92,
            'assistantMessage' => '',
        ], null, Carbon::parse('2026-08-11', 'Africa/Tripoli'));

        $this->assertSame('2026-08-12', $result['slots']['insuranceStart']);
        $this->assertSame('2026-09-01', $result['slots']['insuranceEnd']);
        $this->assertFalse($result['needsClarification']);
    }

    public function test_esim_requires_country(): void
    {
        $validator = new AIResponseValidator();

        $result = $validator->validateIntent([
            'intent' => 'searchEsim',
            'product' => 'esim',
            'missingSlots' => [],
            'needsClarification' => false,
            'confidence' => 0.8,
            'assistantMessage' => '',
        ], null, Carbon::parse('2026-08-11'));

        $this->assertTrue($result['needsClarification']);
        $this->assertSame(['esimCountry'], $result['missingSlots']);
    }

    public function test_capability_question_is_ask_question_not_flight_search(): void
    {
        $service = new GeminiTravelAssistantService(
            $this->createMock(GeminiClient::class),
            new AIResponseValidator(),
            app(\App\Modules\AI\Services\AiSettingsService::class),
        );

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('applyMessageHeuristics');
        $method->setAccessible(true);

        $validated = (new AIResponseValidator())->validateIntent([
            'intent' => 'searchFlight',
            'product' => 'flight',
            'missingSlots' => ['origin', 'destination', 'departureDate', 'adults'],
            'needsClarification' => true,
            'confidence' => 0.9,
            'assistantMessage' => 'من أي مدينة بتسافر؟',
        ], null, Carbon::parse('2026-08-12'));

        $result = $method->invoke(
            $service,
            'هل نقدر نحجز طيران مع تأمين و شفر؟',
            $validated,
        );

        $this->assertSame('askQuestion', $result['intent']);
        $this->assertSame('none', $result['product']);
        $this->assertSame([], $result['missingSlots']);
        $this->assertFalse($result['needsClarification']);
    }

    public function test_multi_product_without_route_is_ask_question(): void
    {
        $service = new GeminiTravelAssistantService(
            $this->createMock(GeminiClient::class),
            new AIResponseValidator(),
            app(\App\Modules\AI\Services\AiSettingsService::class),
        );

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('applyMessageHeuristics');
        $method->setAccessible(true);

        $validated = (new AIResponseValidator())->validateIntent([
            'intent' => 'searchFlight',
            'product' => 'flight',
            'missingSlots' => ['origin', 'destination', 'departureDate', 'adults'],
            'needsClarification' => true,
            'confidence' => 0.9,
            'assistantMessage' => 'من أي مدينة بتسافر؟',
        ], null, Carbon::parse('2026-08-13'));

        $result = $method->invoke(
            $service,
            'يجيب رحلة مع شريحة مع سيت مع انشورانس ترافل بكل سهولة',
            $validated,
        );

        $this->assertSame('askQuestion', $result['intent']);
        $this->assertSame('none', $result['product']);
        $this->assertSame([], $result['missingSlots']);
        $this->assertFalse($result['needsClarification']);
        $this->assertStringContainsString('خطوة بخطوة', $result['assistantMessage']);
    }

    public function test_goodbye_is_cancel_not_flight_search(): void
    {
        $service = new GeminiTravelAssistantService(
            $this->createMock(GeminiClient::class),
            new AIResponseValidator(),
            app(\App\Modules\AI\Services\AiSettingsService::class),
        );

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('applyMessageHeuristics');
        $method->setAccessible(true);

        $validated = (new AIResponseValidator())->validateIntent([
            'intent' => 'searchFlight',
            'product' => 'flight',
            'missingSlots' => ['origin'],
            'needsClarification' => true,
            'confidence' => 0.8,
            'assistantMessage' => 'من وين؟',
        ], null, Carbon::parse('2026-08-13'));

        $result = $method->invoke($service, 'مع السلامة', $validated);

        $this->assertSame('cancel', $result['intent']);
        $this->assertSame('none', $result['product']);
        $this->assertFalse($result['needsClarification']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function mistakeCatalogProvider(): array
    {
        return [
            'multi_product_faq' => ['هل نقدر نحجز طيران مع تأمين و شفر؟', 'askQuestion'],
            'seat_esim_insurance' => ['يجيب رحلة مع شريحة مع سيت مع انشورانس ترافل بكل سهولة', 'askQuestion'],
            'what_can_you_do' => ['شن تسوي؟', 'askQuestion'],
            'do_you_have_esim' => ['عندكم شرائح؟', 'askQuestion'],
            'flight_plus_hotel' => ['نبي طيران مع فندق', 'askQuestion'],
            'seat_only' => ['سيت نافذة', 'askQuestion'],
            'price_question' => ['قداش التذكرة؟', 'askQuestion'],
            'goodbye' => ['مع السلامة', 'cancel'],
            'thanks' => ['شكراً', 'cancel'],
            'enough' => ['كفاية', 'cancel'],
            'end_chat' => ['انهي المحادثة', 'cancel'],
        ];
    }

    #[DataProvider('mistakeCatalogProvider')]
    public function test_mistake_catalog_cases(string $message, string $expectedIntent): void
    {
        $service = new GeminiTravelAssistantService(
            $this->createMock(GeminiClient::class),
            new AIResponseValidator(),
            app(\App\Modules\AI\Services\AiSettingsService::class),
        );

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('applyMessageHeuristics');
        $method->setAccessible(true);

        $validated = (new AIResponseValidator())->validateIntent([
            'intent' => 'searchFlight',
            'product' => 'flight',
            'missingSlots' => ['origin', 'destination', 'departureDate', 'adults'],
            'needsClarification' => true,
            'confidence' => 0.9,
            'assistantMessage' => 'من أي مدينة بتسافر؟',
        ], null, Carbon::parse('2026-08-13'));

        $result = $method->invoke($service, $message, $validated);

        $this->assertSame($expectedIntent, $result['intent'], $message);
        $this->assertSame('none', $result['product'], $message);
        $this->assertSame([], $result['missingSlots'], $message);
        $this->assertFalse($result['needsClarification'], $message);
    }
}
