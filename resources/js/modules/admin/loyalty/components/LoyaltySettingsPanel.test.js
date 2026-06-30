import { flushPromises, mount } from '@vue/test-utils';
import LoyaltySettingsPanel from './LoyaltySettingsPanel.vue';

const initialSettings = {
    loyalty_enabled: true,
    auto_upgrade_enabled: true,
    auto_downgrade_enabled: false,
    visible_in_mobile_app: true,
    allow_discount_stacking: false,
    default_currency: 'LYD',
    max_global_discount_amount: null,
    minimum_discountable_order_amount: null,
    settings_version: 1,
    updated_at: null,
};

describe('LoyaltySettingsPanel', () => {
    test('submits normalized settings payload and shows a success message', async () => {
        const fetchImpl = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                success: true,
                data: {
                    ...initialSettings,
                    allow_discount_stacking: true,
                    default_currency: 'USD',
                    max_global_discount_amount: 150,
                    minimum_discountable_order_amount: 500,
                    settings_version: 2,
                    updated_at: '2026-05-20T10:00:00Z',
                },
            }),
        });

        const wrapper = mount(LoyaltySettingsPanel, {
            props: {
                initialSettings,
                canManage: true,
                updateUrl: '/admin/loyalty/settings',
                fetchImpl,
            },
        });

        await wrapper.get('[data-testid="default-currency"]').setValue('usd');
        await wrapper.get('[data-testid="allow-discount-stacking"]').setValue(true);
        await wrapper.get('[data-testid="max-global-discount"]').setValue('150');
        await wrapper.get('[data-testid="minimum-order-amount"]').setValue('500');
        await wrapper.get('form').trigger('submit.prevent');
        await flushPromises();

        expect(fetchImpl).toHaveBeenCalledTimes(1);
        expect(fetchImpl).toHaveBeenCalledWith(
            '/admin/loyalty/settings',
            expect.objectContaining({
                method: 'PUT',
                headers: expect.objectContaining({
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                }),
                body: JSON.stringify({
                    loyalty_enabled: true,
                    auto_upgrade_enabled: true,
                    auto_downgrade_enabled: false,
                    visible_in_mobile_app: true,
                    allow_discount_stacking: true,
                    default_currency: 'USD',
                    max_global_discount_amount: 150,
                    minimum_discountable_order_amount: 500,
                }),
            }),
        );

        expect(wrapper.get('[data-testid="success-message"]').text()).toContain('Loyalty settings saved successfully.');
        expect(wrapper.get('[data-testid="default-currency"]').element.value).toBe('USD');
    });

    test('does not render the save button without permission', () => {
        const wrapper = mount(LoyaltySettingsPanel, {
            props: {
                initialSettings,
                canManage: false,
                updateUrl: '/admin/loyalty/settings',
            },
        });

        expect(wrapper.find('[data-testid="save-settings"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Only super admins can change global loyalty settings.');
    });
});