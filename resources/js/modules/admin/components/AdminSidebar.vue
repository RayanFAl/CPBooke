<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { navigationItems } from '../config/navigation';
import { useAdminLocale } from '../composables/useAdminLocale';

const page = usePage();
const { t } = useAdminLocale();

const permissions = computed(() => page.props.auth.user?.permissions ?? []);

const isActive = (item) =>
    page.url === item.startsWith || page.url.startsWith(`${item.startsWith}/`);

const canAccessItem = (item) => {
    if (item.permission && !permissions.value.includes(item.permission)) {
        return false;
    }

    return true;
};

const visibleNavigationItems = computed(() => navigationItems.filter(canAccessItem));
</script>

<template>
    <aside class="flex h-full w-full flex-col bg-slate-950 text-slate-100">
        <div class="border-b border-slate-800 px-6 py-5">
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-400">
                {{ t('Booke') }}
            </p>
            <h1 class="mt-2 text-lg font-semibold">{{ t('Control Panel') }}</h1>
            <p class="mt-1 text-sm text-slate-400">
                {{ t('Modular admin foundation.') }}
            </p>
        </div>

        <nav class="flex-1 space-y-1 px-3 py-4">
            <Link
                v-for="item in visibleNavigationItems"
                :key="item.route"
                :href="route(item.route)"
                class="flex items-center rounded-xl px-3 py-2.5 text-sm font-medium transition"
                :class="
                    isActive(item)
                        ? 'bg-slate-800 text-white shadow-lg shadow-slate-950/30'
                        : 'text-slate-300 hover:bg-slate-900 hover:text-white'
                "
            >
                {{ t(item.label) }}
            </Link>
        </nav>

        <div class="border-t border-slate-800 px-6 py-4 text-xs text-slate-400">
            {{ t('Authenticated admin area') }}
        </div>
    </aside>
</template>