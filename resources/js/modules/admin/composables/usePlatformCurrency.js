import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePlatformCurrency(fallback = 'LYD') {
    const page = usePage();

    return computed(() => {
        const value = page.props.platform?.default_currency;

        if (typeof value === 'string' && value.trim() !== '') {
            return value.trim().toUpperCase();
        }

        return fallback;
    });
}
