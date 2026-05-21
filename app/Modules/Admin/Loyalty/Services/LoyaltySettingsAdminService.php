<?php

namespace App\Modules\Admin\Loyalty\Services;

use App\Models\LoyaltySetting;
use App\Models\User;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltySettingsRequest;
use App\Support\Rbac\RbacAuditLogger;

class LoyaltySettingsAdminService
{
    public function __construct(
        private readonly RbacAuditLogger $rbacAuditLogger,
    ) {
    }

    public function getSettings(): LoyaltySetting
    {
        return LoyaltySetting::current();
    }

    public function update(UpdateLoyaltySettingsRequest $request, User $admin): LoyaltySetting
    {
        $settings = LoyaltySetting::current();
        $settings->forceFill($request->validated());
        $settings->settings_version = max(1, (int) ($settings->settings_version ?? 1)) + 1;
        $settings->updated_by_user_id = $admin->id;
        $settings->save();

        $this->rbacAuditLogger->log(
            'loyalty.settings.updated',
            'loyalty.settings.manage',
            $admin,
            'loyalty_settings',
            $settings->id,
            [
                'settings_version' => $settings->settings_version,
                'default_currency' => $settings->default_currency,
                'loyalty_enabled' => (bool) $settings->loyalty_enabled,
                'auto_upgrade_enabled' => (bool) $settings->auto_upgrade_enabled,
                'auto_downgrade_enabled' => (bool) $settings->auto_downgrade_enabled,
                'visible_in_mobile_app' => (bool) $settings->visible_in_mobile_app,
                'allow_discount_stacking' => (bool) $settings->allow_discount_stacking,
            ],
        );

        return $settings->refresh();
    }
}