<?php

namespace App\Modules\Orders\Services;

use App\Models\Order;
use App\Models\Provider;
use App\Modules\Settings\Services\SystemSettingsService;

/**
 * Commercial margin resolution order (V1):
 * 1) Payload hints (commission_amount / base_amount)
 * 2) Provider.commission_rate
 * 3) SystemSetting.default_commission_percent (platform default)
 * 4) Zero margin (supplier_cost = selling)
 *
 * Historical order financial rows are never rewritten by settings changes.
 */
class OrderCostService
{
    public function __construct(
        private readonly SystemSettingsService $systemSettingsService,
    ) {
    }

    /**
     * @param  array{commission_amount?: string|float|null, base_amount?: string|float|null}  $hints
     * @return array{
     *     selling_price: string,
     *     supplier_cost: string,
     *     commission_amount: string,
     *     markup_amount: string,
     *     profit_amount: string,
     *     margin_percent: string
     * }
     */
    public function resolve(Order $order, ?Provider $provider = null, array $hints = []): array
    {
        $selling = $this->money($order->final_amount)
            ?? $this->money($order->total_amount)
            ?? 0.0;

        $hintCommission = $this->money($hints['commission_amount'] ?? null);
        $hintBase = $this->money($hints['base_amount'] ?? $order->base_amount);

        if ($hintCommission !== null) {
            $commission = max(0, $hintCommission);
            $supplierCost = $hintBase !== null
                ? max(0, $hintBase)
                : max(0, $selling - $commission);
        } elseif ($hintBase !== null) {
            $supplierCost = max(0, $hintBase);
            $commission = max(0, $selling - $supplierCost);
        } elseif ($provider && $provider->commission_rate !== null) {
            $rate = (float) $provider->commission_rate;
            $commission = round($selling * ($rate / 100), 2);
            $supplierCost = max(0, round($selling - $commission, 2));
        } elseif (($defaultRate = $this->systemSettingsService->defaultCommissionPercent()) !== null) {
            $commission = round($selling * ($defaultRate / 100), 2);
            $supplierCost = max(0, round($selling - $commission, 2));
        } else {
            $supplierCost = $selling;
            $commission = 0.0;
        }

        $markup = round($selling - $supplierCost, 2);
        $profit = $markup;
        $margin = $selling > 0 ? round(($profit / $selling) * 100, 2) : 0.0;

        return [
            'selling_price' => $this->format($selling),
            'supplier_cost' => $this->format($supplierCost),
            'commission_amount' => $this->format($commission),
            'markup_amount' => $this->format($markup),
            'profit_amount' => $this->format($profit),
            'margin_percent' => $this->format($margin),
        ];
    }

    /**
     * @param  array{commission_amount?: string|float|null, base_amount?: string|float|null}  $hints
     */
    public function apply(Order $order, ?Provider $provider = null, array $hints = []): Order
    {
        $resolved = $this->resolve($order, $provider, $hints);

        $order->forceFill([
            'provider_id' => $provider?->id ?? $order->provider_id,
            ...$resolved,
        ])->save();

        return $order->refresh();
    }

    public function debitAmount(Order $order): float
    {
        $supplierCost = $this->money($order->supplier_cost);

        if ($supplierCost !== null && $supplierCost > 0) {
            return $supplierCost;
        }

        return $this->money($order->total_amount) ?? 0.0;
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function format(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
