<?php

namespace Tests\Unit;

use App\Models\ExchangeRate;
use App\Modules\ExchangeRates\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedRates(): void
    {
        ExchangeRate::query()->create([
            'currency_code' => 'LYD',
            'buy_rate_to_lyd' => '1.00000000',
            'sell_rate_to_lyd' => '1.00000000',
            'rate_to_lyd' => '1.00000000',
            'is_active' => true,
        ]);
        ExchangeRate::query()->create([
            'currency_code' => 'USD',
            'buy_rate_to_lyd' => '6.40000000',
            'sell_rate_to_lyd' => '6.60000000',
            'rate_to_lyd' => '6.50000000',
            'is_active' => true,
        ]);
        ExchangeRate::query()->create([
            'currency_code' => 'EUR',
            'buy_rate_to_lyd' => '7.50000000',
            'sell_rate_to_lyd' => '7.70000000',
            'rate_to_lyd' => '7.60000000',
            'is_active' => true,
        ]);
    }

    public function test_same_currency_conversion_is_identity(): void
    {
        $this->seedRates();

        $result = app(ExchangeRateService::class)->convert(50, 'USD', 'USD');

        $this->assertEqualsWithDelta(50.0, $result['converted_amount'], 0.00000001);
        $this->assertEqualsWithDelta(1.0, $result['rate'], 0.00000001);
    }

    public function test_mid_lyd_conversions(): void
    {
        $this->seedRates();
        $service = app(ExchangeRateService::class);

        $toUsd = $service->convert(13, 'LYD', 'USD', 'mid');
        $this->assertEqualsWithDelta(2.0, $toUsd['converted_amount'], 0.00000001);

        $toLyd = $service->convert(2, 'USD', 'LYD', 'mid');
        $this->assertEqualsWithDelta(13.0, $toLyd['converted_amount'], 0.00000001);
    }

    public function test_auto_conversion_uses_buy_then_sell(): void
    {
        $this->seedRates();

        // 100 EUR → USD auto = 100 * 7.5 / 6.6
        $result = app(ExchangeRateService::class)->convert(100, 'EUR', 'USD', 'auto');

        $this->assertSame('auto', $result['side']);
        $this->assertEqualsWithDelta(113.63636364, $result['converted_amount'], 0.00000001);
    }
}
