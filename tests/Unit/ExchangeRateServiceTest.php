<?php

namespace Tests\Unit;

use App\Models\ExchangeRate;
use App\Modules\ExchangeRates\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_currency_conversion_is_identity(): void
    {
        ExchangeRate::query()->create([
            'currency_code' => 'LYD',
            'rate_to_lyd' => '1.00000000',
            'is_active' => true,
        ]);
        ExchangeRate::query()->create([
            'currency_code' => 'USD',
            'rate_to_lyd' => '6.50000000',
            'is_active' => true,
        ]);
        ExchangeRate::query()->create([
            'currency_code' => 'EUR',
            'rate_to_lyd' => '7.60000000',
            'is_active' => true,
        ]);

        $result = app(ExchangeRateService::class)->convert(50, 'USD', 'USD');

        $this->assertEqualsWithDelta(50.0, $result['converted_amount'], 0.00000001);
        $this->assertEqualsWithDelta(1.0, $result['rate'], 0.00000001);
    }

    public function test_lyd_to_usd_and_usd_to_lyd(): void
    {
        ExchangeRate::query()->create([
            'currency_code' => 'LYD',
            'rate_to_lyd' => '1.00000000',
            'is_active' => true,
        ]);
        ExchangeRate::query()->create([
            'currency_code' => 'USD',
            'rate_to_lyd' => '6.50000000',
            'is_active' => true,
        ]);
        ExchangeRate::query()->create([
            'currency_code' => 'EUR',
            'rate_to_lyd' => '7.60000000',
            'is_active' => true,
        ]);

        $service = app(ExchangeRateService::class);

        $toUsd = $service->convert(13, 'LYD', 'USD');
        $this->assertEqualsWithDelta(2.0, $toUsd['converted_amount'], 0.00000001);

        $toLyd = $service->convert(2, 'USD', 'LYD');
        $this->assertEqualsWithDelta(13.0, $toLyd['converted_amount'], 0.00000001);
    }
}
