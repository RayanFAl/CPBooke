<?php

namespace App\Modules\Pricing\DTO;

final readonly class PricingAdjustmentData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $sourceType,
        public ?int $sourceId,
        public ?string $code,
        public string $label,
        public string $adjustmentType,
        public ?string $valueType,
        public ?string $configuredValue,
        public string $appliedAmount,
        public string $currency,
        public int $priority = 0,
        public array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'code' => $this->code,
            'label' => $this->label,
            'adjustment_type' => $this->adjustmentType,
            'value_type' => $this->valueType,
            'configured_value' => $this->configuredValue,
            'applied_amount' => $this->appliedAmount,
            'currency' => $this->currency,
            'priority' => $this->priority,
            'metadata' => $this->metadata,
        ];
    }
}