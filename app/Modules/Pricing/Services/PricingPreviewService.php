<?php

namespace App\Modules\Pricing\Services;

use App\Models\LoyaltySetting;
use App\Models\User;
use App\Modules\Pricing\DTO\PricingAdjustmentData;
use App\Modules\Pricing\DTO\PricingContext;
use Illuminate\Support\Arr;

class PricingPreviewService
{
    public function __construct(
        private readonly PricingEngine $pricingEngine,
        private readonly PricingVersionService $pricingVersionService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function preview(array $payload, ?User $actor = null): array
    {
        $user = $this->resolveUser(Arr::get($payload, 'user_id'), $actor);
        $currency = $this->resolveCurrency(Arr::get($payload, 'currency'));

        $context = new PricingContext(
            user: $user,
            serviceType: (string) Arr::get($payload, 'service_type'),
            currency: $currency,
            baseAmount: $this->formatAmount((float) Arr::get($payload, 'base_amount')),
            source: 'preview',
            attributes: array_merge(
                Arr::get($payload, 'attributes', []),
                ['provider_name' => Arr::get($payload, 'provider_name', 'default')],
            ),
        );

        $result = $this->pricingEngine->price($context);
        $discountTotal = $this->sumAdjustments($result->appliedAdjustments);
        $baseAmount = $this->formatAmount((float) $result->baseAmount);
        $finalAmount = $this->formatAmount(max(0, round((float) $baseAmount - (float) $discountTotal, 2)));

        return [
            'base_amount' => (float) $baseAmount,
            'discount_total' => (float) $discountTotal,
            'final_amount' => (float) $finalAmount,
            'currency' => $result->currency,
            'pricing_version' => $result->pricingVersion !== '' ? $result->pricingVersion : $this->pricingVersionService->resolve(),
            'adjustments' => array_map(
                fn (PricingAdjustmentData $adjustment): array => [
                    'source_type' => $adjustment->sourceType,
                    'code' => $adjustment->code,
                    'label' => $adjustment->label,
                    'adjustment_type' => $adjustment->valueType ?? $adjustment->adjustmentType,
                    'applied_amount' => (float) $this->formatAmount((float) $adjustment->appliedAmount),
                ],
                $result->appliedAdjustments,
            ),
        ];
    }

    private function resolveUser(mixed $userId, ?User $actor): ?User
    {
        if (! is_numeric($userId)) {
            return $actor;
        }

        return User::query()->find((int) $userId);
    }

    private function resolveCurrency(mixed $currency): string
    {
        if (is_string($currency) && $currency !== '') {
            return strtoupper($currency);
        }

        return (string) LoyaltySetting::current()->default_currency;
    }

    /**
     * @param  array<int, PricingAdjustmentData>  $adjustments
     */
    private function sumAdjustments(array $adjustments): string
    {
        $total = array_reduce(
            $adjustments,
            fn (float $carry, PricingAdjustmentData $adjustment): float => $carry + max(0, (float) $adjustment->appliedAmount),
            0.0,
        );

        return $this->formatAmount($total);
    }

    private function formatAmount(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}