<?php

namespace Tests\Unit\Pricing;

use App\Models\User;
use App\Modules\Pricing\DTO\PricingAdjustmentData;
use App\Modules\Pricing\DTO\PricingResult;
use App\Modules\Pricing\Services\PricingEngine;
use App\Modules\Pricing\Services\PricingPreviewService;
use App\Modules\Pricing\Services\PricingVersionService;
use Mockery;
use Tests\TestCase;

class PricingPreviewServiceTest extends TestCase
{
    public function test_preview_uses_engine_and_returns_expected_shape(): void
    {
        $engine = Mockery::mock(PricingEngine::class);
        $engine->shouldReceive('price')->once()->andReturn(new PricingResult(
            serviceType: 'hotel',
            currency: 'LYD',
            baseAmount: '1200.00',
            discountTotal: '0.00',
            finalAmount: '1200.00',
            pricingVersion: 'pricing:v1|loyalty:7',
            appliedAdjustments: [
                new PricingAdjustmentData(
                    sourceType: 'loyalty',
                    sourceId: 10,
                    code: 'gold_discount',
                    label: 'Gold Tier Discount',
                    adjustmentType: 'discount',
                    valueType: 'percentage',
                    configuredValue: '7.00',
                    appliedAmount: '84.00',
                    currency: 'LYD',
                    priority: 100,
                ),
            ],
        ));

        $versionService = Mockery::mock(PricingVersionService::class);
        $versionService->shouldReceive('resolve')->never();

        $service = new PricingPreviewService($engine, $versionService);

        $result = $service->preview([
            'service_type' => 'hotel',
            'currency' => 'LYD',
            'base_amount' => 1200,
            'provider_name' => 'default',
            'attributes' => ['allow_finance_sensitive' => false],
        ]);

        $this->assertSame([
            'base_amount' => 1200.0,
            'fare_amount' => 1200.0,
            'tax_amount' => 0.0,
            'discount_total' => 84.0,
            'final_amount' => 1116.0,
            'currency' => 'LYD',
            'pricing_version' => 'pricing:v1|loyalty:7',
            'adjustments' => [
                [
                    'source_type' => 'loyalty',
                    'code' => 'gold_discount',
                    'label' => 'Gold Tier Discount',
                    'adjustment_type' => 'percentage',
                    'applied_amount' => 84.0,
                ],
            ],
        ], $result);
    }

    public function test_preview_uses_authenticated_actor_when_user_id_missing(): void
    {
        $actor = User::factory()->make(['id' => 55]);

        $engine = Mockery::mock(PricingEngine::class);
        $engine->shouldReceive('price')->once()->withArgs(function ($context) use ($actor): bool {
            return $context->user?->id === $actor->id
                && $context->serviceType === 'flight'
                && $context->baseAmount === '200.00';
        })->andReturn(new PricingResult(
            serviceType: 'flight',
            currency: 'LYD',
            baseAmount: '200.00',
            discountTotal: '0.00',
            finalAmount: '200.00',
            pricingVersion: 'pricing:v1|loyalty:7',
            appliedAdjustments: [],
        ));

        $versionService = Mockery::mock(PricingVersionService::class);
        $versionService->shouldReceive('resolve')->never();

        $service = new PricingPreviewService($engine, $versionService);

        $result = $service->preview([
            'service_type' => 'flight',
            'currency' => 'LYD',
            'base_amount' => 200,
        ], $actor);

        $this->assertSame(200.0, $result['base_amount']);
        $this->assertSame(200.0, $result['fare_amount']);
        $this->assertSame(0.0, $result['tax_amount']);
        $this->assertSame(0.0, $result['discount_total']);
        $this->assertSame(200.0, $result['final_amount']);
    }
}