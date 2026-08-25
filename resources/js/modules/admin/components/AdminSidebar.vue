<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { navigationItems } from '../config/navigation';
import { useAdminLocale } from '../composables/useAdminLocale';

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle-collapse', 'navigate']);

const page = usePage();
const { t, isArabic } = useAdminLocale();

const user = computed(() => page.props.auth.user);
const permissions = computed(() => user.value?.permissions ?? []);
const companyName = computed(() => page.props.platform?.company_name || t('Booke'));

const displayName = computed(() => user.value?.full_name ?? user.value?.name ?? '');

const userInitials = computed(() => {
    const name = displayName.value.trim();

    if (!name) {
        return '?';
    }

    const parts = name.split(/\s+/).filter(Boolean);

    if (parts.length >= 2) {
        return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
    }

    return name.slice(0, 2).toUpperCase();
});

const isActive = (item) =>
    page.url === item.startsWith || page.url.startsWith(`${item.startsWith}/`);

const canAccessItem = (item) => {
    if (item.permission && !permissions.value.includes(item.permission)) {
        return false;
    }

    return true;
};

const visibleNavigationItems = computed(() =>
    navigationItems
        .map((item) => {
            if (!item.children) {
                return canAccessItem(item) ? item : null;
            }

            const children = item.children.filter(canAccessItem);

            if (children.length === 0) {
                return null;
            }

            return { ...item, children };
        })
        .filter(Boolean),
);

const openGroups = ref({});

const groupHasActiveChild = (group) =>
    (group.children ?? []).some((child) => isActive(child));

const isGroupOpen = (group) => {
    if (props.collapsed) {
        return false;
    }

    if (openGroups.value[group.label] !== undefined) {
        return openGroups.value[group.label];
    }

    return groupHasActiveChild(group);
};

const toggleGroup = (group) => {
    if (props.collapsed) {
        emit('toggle-collapse');
        openGroups.value = {
            ...openGroups.value,
            [group.label]: true,
        };
        return;
    }

    openGroups.value = {
        ...openGroups.value,
        [group.label]: !isGroupOpen(group),
    };
};

watch(
    () => page.url,
    () => {
        visibleNavigationItems.value.forEach((item) => {
            if (item.children && groupHasActiveChild(item)) {
                openGroups.value = {
                    ...openGroups.value,
                    [item.label]: true,
                };
            }
        });
    },
    { immediate: true },
);

const iconPaths = {
    dashboard: 'M3 4a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V4zM3 14a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H4a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z',
    users: 'M7 8a3 3 0 116 0 3 3 0 01-6 0zm-4 8a4 4 0 018 0v1H3v-1zm9-1a4 4 0 018 0v1h-2v-1a6 6 0 00-3.5-5.47A5.98 5.98 0 0014 9a6 6 0 00-6 6v1h2v-1z',
    orders: 'M4 4h12v2H4V4zm0 4h12v10H4V8zm3 2v2h6v-2H7z',
    finance: 'M4 5.5A1.5 1.5 0 015.5 4h9A1.5 1.5 0 0116 5.5v9A1.5 1.5 0 0114.5 16h-9A1.5 1.5 0 014 14.5v-9zM7 8h6v1.5H7V8zm0 3h4v1.5H7V11z',
    governance: 'M10 2.5l6.5 3.75v7.5L10 17.5l-6.5-3.75v-7.5L10 2.5zm0 2.2L6 7.35v5.3l4 2.15 4-2.15v-5.3L10 4.7z',
    support: 'M3 5.5A2.5 2.5 0 015.5 3h9A2.5 2.5 0 0117 5.5v6A2.5 2.5 0 0114.5 14H9l-3.5 2.5V14H5.5A2.5 2.5 0 013 11.5v-6z',
    airports: 'M10 2a1 1 0 01.9.55l1.2 2.43 2.7.39a1 1 0 01.55 1.7l-1.95 1.9.46 2.69A1 1 0 0111.7 12l-2.4-1.26L7 12a1 1 0 01-.9-1.34l.46-2.69-1.95-1.9a1 1 0 01.55-1.7l2.7-.39L9.1 2.55A1 1 0 0110 2z',
    loyalty: 'M10 2.2l1.82 3.69 4.07.59-2.95 2.88.7 4.06L10 11.77l-3.64 1.69.7-4.06-2.95-2.88 4.07-.59L10 2.2z',
    notifications: 'M10 2a4 4 0 00-4 4v2.26A6 6 0 004 14h12a6 6 0 00-2-5.74V6a4 4 0 00-4-4zm0 16a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0010 18z',
    suppliers: 'M4 3.5A1.5 1.5 0 015.5 2h9A1.5 1.5 0 0116 3.5V6h1.25a.75.75 0 010 1.5H16v8A1.5 1.5 0 0114.5 17h-9A1.5 1.5 0 014 15.5v-8H2.75a.75.75 0 010-1.5H4V3.5zM5.5 3.5V6h9V3.5h-9zm0 4v8h9v-8h-9z',
    search: 'M8.5 3a5.5 5.5 0 104.383 8.823l3.09 3.09a.75.75 0 101.06-1.06l-3.09-3.09A5.5 5.5 0 008.5 3zm-4 5.5a4 4 0 118 0 4 4 0 01-8 0z',
    monitoring: 'M10 2a8 8 0 100 16 8 8 0 000-16zm0 3a.75.75 0 01.75.75v3.5l2.5 1.5a.75.75 0 11-.75 1.3l-2.875-1.725A.75.75 0 019.25 9.25V5.75A.75.75 0 0110 5z',
    health: 'M10 3.5a6.5 6.5 0 00-5.995 9.082L2.5 14.5l2.418-.505A6.5 6.5 0 1010 3.5zm-2.25 3a.75.75 0 000 1.5h1.5v1.5a.75.75 0 001.5 0V8h1.5a.75.75 0 000-1.5H11V5.5a.75.75 0 00-1.5 0V6.5H8.75a.75.75 0 00-.75-.75z',
};

const handleNavigate = () => {
    emit('navigate');
};
</script>

<template>
    <aside class="flex h-full w-full flex-col bg-slate-950 text-slate-100">
        <div
            class="flex items-start gap-2 border-b border-slate-800 px-3 py-4"
            :class="collapsed ? 'justify-center' : 'px-4'"
        >
            <div class="min-w-0 flex-1" :class="collapsed ? 'sr-only' : ''">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-400">
                    {{ companyName }}
                </p>
                <h1 class="mt-2 text-lg font-semibold">{{ t('Control Panel') }}</h1>
            </div>

            <button
                type="button"
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-900 hover:text-white"
                :title="collapsed ? t('Expand sidebar') : t('Collapse sidebar')"
                :aria-label="collapsed ? t('Expand sidebar') : t('Collapse sidebar')"
                @click="emit('toggle-collapse')"
            >
                <svg
                    class="h-5 w-5 transition"
                    :class="[
                        collapsed ? 'rotate-180' : '',
                        isArabic ? 'scale-x-[-1]' : '',
                    ]"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        fill-rule="evenodd"
                        d="M11.78 15.78a.75.75 0 01-1.06 0L5.47 10.53a.75.75 0 010-1.06l5.25-5.25a.75.75 0 111.06 1.06L7.06 10l4.72 4.72a.75.75 0 010 1.06z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-2 py-4">
            <template v-for="item in visibleNavigationItems" :key="item.label">
                <Link
                    v-if="!item.children"
                    :href="route(item.route)"
                    class="flex items-center rounded-xl text-sm font-medium transition"
                    :class="[
                        collapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5',
                        isActive(item)
                            ? 'bg-slate-800 text-white shadow-lg shadow-slate-950/30'
                            : 'text-slate-300 hover:bg-slate-900 hover:text-white',
                    ]"
                    :title="collapsed ? t(item.label) : undefined"
                    @click="handleNavigate"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path :d="iconPaths[item.icon] ?? iconPaths.dashboard" />
                    </svg>
                    <span v-if="!collapsed" class="truncate">{{ t(item.label) }}</span>
                </Link>

                <div v-else class="space-y-1">
                    <button
                        type="button"
                        class="flex w-full items-center rounded-xl text-sm font-medium text-slate-300 transition hover:bg-slate-900 hover:text-white"
                        :class="[
                            collapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5',
                            groupHasActiveChild(item) ? 'text-white' : '',
                        ]"
                        :title="collapsed ? t(item.label) : undefined"
                        :aria-expanded="isGroupOpen(item)"
                        @click="toggleGroup(item)"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path :d="iconPaths[item.icon] ?? iconPaths.dashboard" />
                        </svg>
                        <span v-if="!collapsed" class="min-w-0 flex-1 truncate text-start">{{ t(item.label) }}</span>
                        <svg
                            v-if="!collapsed"
                            class="h-4 w-4 shrink-0 text-slate-500 transition"
                            :class="isGroupOpen(item) ? 'rotate-90' : ''"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>

                    <div
                        v-if="!collapsed && isGroupOpen(item)"
                        class="ms-3 space-y-1 border-s border-slate-800 ps-2"
                    >
                        <Link
                            v-for="child in item.children"
                            :key="child.route"
                            :href="route(child.route)"
                            class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition"
                            :class="isActive(child)
                                ? 'bg-slate-800 text-white'
                                : 'text-slate-400 hover:bg-slate-900 hover:text-white'"
                            @click="handleNavigate"
                        >
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path :d="iconPaths[child.icon] ?? iconPaths.dashboard" />
                            </svg>
                            <span class="truncate">{{ t(child.label) }}</span>
                        </Link>
                    </div>
                </div>
            </template>
        </nav>

        <div class="border-t border-slate-800 p-2">
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="flex w-full items-center rounded-xl text-sm font-medium text-slate-300 transition hover:bg-slate-900 hover:text-white"
                :class="collapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'"
                :title="collapsed ? t('Logout') : undefined"
            >
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        fill-rule="evenodd"
                        d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z"
                        clip-rule="evenodd"
                    />
                    <path
                        fill-rule="evenodd"
                        d="M19 10a.75.75 0 00-.75-.75H8.704l1.048-.943a.75.75 0 10-1.004-1.114l-2.5 2.25a.75.75 0 000 1.114l2.5 2.25a.75.75 0 101.004-1.114l-1.048-.943h9.546A.75.75 0 0019 10z"
                        clip-rule="evenodd"
                    />
                </svg>
                <span v-if="!collapsed">{{ t('Logout') }}</span>
            </Link>
        </div>
    </aside>
</template>
