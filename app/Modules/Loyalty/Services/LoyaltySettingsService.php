<?php

namespace App\Modules\Loyalty\Services;

use App\Models\LoyaltySetting;

class LoyaltySettingsService
{
    public function current(): LoyaltySetting
    {
        return LoyaltySetting::current();
    }

    public function isEnabled(): bool
    {
        return (bool) $this->current()->loyalty_enabled;
    }

    public function settingsVersion(): int
    {
        return (int) ($this->current()->settings_version ?? 1);
    }

    public function allowsDiscountStacking(): bool
    {
        return (bool) $this->current()->allow_discount_stacking;
    }
}