import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { pageHelpEntries } from '../config/pageHelp';
import { useAdminLocale } from './useAdminLocale';

const matchHelp = (routeName, path) => {
    const name = typeof routeName === 'string' ? routeName : '';
    const url = typeof path === 'string' ? path.split('?')[0] : '';

    return pageHelpEntries.find((entry) => {
        const key = entry.match;

        if (name === key || name.startsWith(`${key}.`)) {
            return true;
        }

        const pathPrefix = `/${key.replaceAll('.', '/')}`;

        return url === pathPrefix || url.startsWith(`${pathPrefix}/`);
    }) ?? null;
};

export function useAdminPageHelp() {
    const page = usePage();
    const { locale } = useAdminLocale();

    const entry = computed(() => {
        let routeName = '';

        try {
            routeName = typeof route === 'function' ? (route().current() || '') : '';
        } catch {
            routeName = '';
        }

        return matchHelp(routeName, page.url);
    });

    const helpText = computed(() => {
        const current = entry.value;

        if (!current) {
            return '';
        }

        return locale.value === 'ar' ? current.ar : current.en;
    });

    return {
        helpText,
    };
}
