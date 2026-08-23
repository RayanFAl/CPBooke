import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

export const adminNavigating = ref(false);

let listenersBound = false;

export function bindAdminNavigation() {
    if (listenersBound || typeof window === 'undefined') {
        return;
    }

    listenersBound = true;

    router.on('start', () => {
        adminNavigating.value = true;
    });

    router.on('finish', () => {
        adminNavigating.value = false;
    });

    router.on('error', () => {
        adminNavigating.value = false;
    });
}

export function useAdminNavigation() {
    bindAdminNavigation();

    return {
        navigating: adminNavigating,
    };
}
