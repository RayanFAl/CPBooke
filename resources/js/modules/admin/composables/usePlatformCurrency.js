import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Platform default currency from SystemSetting (shared via Inertia).
 * Returns a computed string and also `{ defaultCurrency, formatMoney }` for older callers.
 */
export function usePlatformCurrency(fallback = 'LYD') {
    const page = usePage();

    const defaultCurrency = computed(() => {
        const value = page.props.platform?.default_currency;

        return typeof value === 'string' && value.trim() !== ''
            ? value.trim().toUpperCase()
            : fallback;
    });

    const formatMoney = (amount, currency = null) => {
        const code = currency || defaultCurrency.value || fallback;

        try {
            return new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: code,
            }).format(Number(amount ?? 0));
        } catch {
            return `${amount ?? 0} ${code}`;
        }
    };

    return Object.assign(defaultCurrency, {
        defaultCurrency,
        formatMoney,
    });
}
