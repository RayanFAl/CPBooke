<?php

namespace App\Modules\Admin\Loyalty\Services;

use App\Models\AuditLog;
use App\Models\LoyaltySetting;
use App\Models\User;
use App\Modules\Admin\Loyalty\Http\Requests\UpdateLoyaltySettingsRequest;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Loyalty\Support\LoyaltyResultsPromo;
use App\Support\Rbac\RbacAuditLogger;

class LoyaltySettingsAdminService
{
    public function __construct(
        private readonly RbacAuditLogger $rbacAuditLogger,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function getSettings(): LoyaltySetting
    {
        return LoyaltySetting::current();
    }

    public function update(UpdateLoyaltySettingsRequest $request, User $admin): LoyaltySetting
    {
        $settings = LoyaltySetting::current();
        $before = $this->settingsSnapshot($settings);

        $validated = $request->validated();
        $resultsPromo = $validated['results_promo'] ?? null;
        unset($validated['results_promo']);

        if (is_array($resultsPromo)) {
            $metadata = is_array($settings->metadata) ? $settings->metadata : [];
            $metadata['results_promo'] = LoyaltyResultsPromo::resolve($resultsPromo);
            $validated['metadata'] = $metadata;
        }

        $settings->forceFill($validated);
        $settings->settings_version = max(1, (int) ($settings->settings_version ?? 1)) + 1;
        $settings->updated_by_user_id = $admin->id;
        $settings->save();
        $settings = $settings->refresh();

        $after = $this->settingsSnapshot($settings);
        [$oldValues, $newValues] = $this->diffSnapshots($before, $after);

        $this->rbacAuditLogger->log(
            'loyalty.settings.updated',
            'loyalty.settings.manage',
            $admin,
            'loyalty_settings',
            $settings->id,
            [
                'settings_version' => $settings->settings_version,
                'before' => $oldValues,
                'after' => $newValues,
            ],
        );

        if ($oldValues !== [] || $newValues !== []) {
            $this->auditRecorder->success(
                AuditLog::MODULE_LOYALTY,
                'loyalty.settings.updated',
                'Loyalty settings updated',
                AuditLog::ENTITY_LOYALTY_SETTINGS,
                $settings->id,
                $admin,
                $oldValues,
                $newValues,
                [
                    'source' => 'admin.loyalty',
                    'settings_version' => $settings->settings_version,
                ],
            );
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsPayload(LoyaltySetting $settings): array
    {
        $metadata = is_array($settings->metadata) ? $settings->metadata : [];

        return [
            'loyalty_enabled' => (bool) $settings->loyalty_enabled,
            'auto_upgrade_enabled' => (bool) $settings->auto_upgrade_enabled,
            'auto_downgrade_enabled' => (bool) $settings->auto_downgrade_enabled,
            'visible_in_mobile_app' => (bool) $settings->visible_in_mobile_app,
            'allow_discount_stacking' => (bool) $settings->allow_discount_stacking,
            'default_currency' => (string) ($settings->default_currency ?: 'LYD'),
            'max_global_discount_amount' => $settings->max_global_discount_amount,
            'minimum_discountable_order_amount' => $settings->minimum_discountable_order_amount,
            'settings_version' => (int) $settings->settings_version,
            'updated_at' => $settings->updated_at?->toIso8601String(),
            'results_promo' => LoyaltyResultsPromo::resolve(
                is_array($metadata['results_promo'] ?? null) ? $metadata['results_promo'] : null,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsSnapshot(LoyaltySetting $settings): array
    {
        $payload = $this->settingsPayload($settings);
        unset($payload['updated_at']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function diffSnapshots(array $before, array $after): array
    {
        $oldValues = [];
        $newValues = [];

        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;

            if ($key === 'results_promo') {
                if (json_encode($previous) === json_encode($value)) {
                    continue;
                }

                $oldValues[$key] = $previous;
                $newValues[$key] = $value;

                continue;
            }

            if (is_bool($previous) || is_bool($value)) {
                if ((bool) $previous === (bool) $value) {
                    continue;
                }
            } elseif ((string) ($previous ?? '') === (string) ($value ?? '')) {
                continue;
            }

            $oldValues[$key] = $previous;
            $newValues[$key] = $value;
        }

        return [$oldValues, $newValues];
    }
}
