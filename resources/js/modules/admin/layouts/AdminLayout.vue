<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AdminSidebar from '../components/AdminSidebar.vue';
import AdminBreadcrumbs from '../components/AdminBreadcrumbs.vue';
import AdminToastHost from '../components/AdminToastHost.vue';
import AdminConfirmDialog from '../components/AdminConfirmDialog.vue';
import AdminGlobalSearch from '../components/AdminGlobalSearch.vue';
import { bindAdminNavigation } from '../composables/useAdminNavigation';
import { useAdminLocale } from '../composables/useAdminLocale';
import { useAdminToast } from '../composables/useAdminToast';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const SIDEBAR_STORAGE_KEY = 'admin-sidebar-collapsed';

const page = usePage();
const mobileSidebarOpen = ref(false);
const desktopSidebarCollapsed = ref(
    typeof window !== 'undefined' && window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1',
);
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash ?? {});
const toast = useAdminToast();
const { locale, isArabic, setLocale, t } = useAdminLocale();
const translatedTitle = computed(() => t(props.title));
const translatedDescription = computed(() => t(props.description));

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

const toggleLocale = () => {
    setLocale(locale.value === 'ar' ? 'en' : 'ar');
};

const localeToggleLabel = computed(() => (
    locale.value === 'ar' ? t('Switch to English') : t('Switch to Arabic')
));

const toggleDesktopSidebar = () => {
    desktopSidebarCollapsed.value = !desktopSidebarCollapsed.value;
};

const openMobileSidebar = () => {
    mobileSidebarOpen.value = true;
};

const closeMobileSidebar = () => {
    mobileSidebarOpen.value = false;
};

watch(desktopSidebarCollapsed, (collapsed) => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? '1' : '0');
});

watch(
    flash,
    (value) => {
        if (value.success) {
            toast.success(value.success);
        }

        if (value.error) {
            toast.error(value.error);
        }
    },
    { immediate: true },
);

onMounted(() => {
    bindAdminNavigation();
});
</script>

<template>
    <div class="admin-shell min-h-screen bg-slate-100 text-slate-900" :dir="isArabic ? 'rtl' : 'ltr'">
        <a
            href="#main-content"
            class="skip-to-content"
        >
            {{ t('Skip to main content') }}
        </a>

        <div class="flex min-h-screen">
            <div
                class="hidden shrink-0 transition-[width] duration-300 lg:block"
                :class="desktopSidebarCollapsed ? 'w-[4.5rem]' : 'w-72'"
            >
                <AdminSidebar
                    :collapsed="desktopSidebarCollapsed"
                    @toggle-collapse="toggleDesktopSidebar"
                />
            </div>

            <div
                v-if="mobileSidebarOpen"
                class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden"
                @click="closeMobileSidebar"
            />

            <div
                class="fixed inset-y-0 start-0 z-50 w-72 transform transition lg:hidden"
                :class="mobileSidebarOpen ? 'translate-x-0' : (isArabic ? 'translate-x-full' : '-translate-x-full')"
            >
                <AdminSidebar
                    :collapsed="false"
                    @toggle-collapse="closeMobileSidebar"
                    @navigate="closeMobileSidebar"
                />
            </div>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="border-b border-slate-200 bg-white/90 backdrop-blur">
                    <div class="flex items-center justify-between gap-4 px-5 py-4 sm:px-8">
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-700 transition hover:bg-slate-100 lg:hidden"
                                @click="openMobileSidebar"
                            >
                                <span class="sr-only">{{ t('Open navigation') }}</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path
                                        fill-rule="evenodd"
                                        d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4A1 1 0 013 5zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm1 4a1 1 0 100 2h12a1 1 0 100-2H4z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>

                            <button
                                type="button"
                                class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-700 transition hover:bg-slate-100 lg:inline-flex"
                                :title="desktopSidebarCollapsed ? t('Expand sidebar') : t('Collapse sidebar')"
                                :aria-label="desktopSidebarCollapsed ? t('Expand sidebar') : t('Collapse sidebar')"
                                @click="toggleDesktopSidebar"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path
                                        fill-rule="evenodd"
                                        d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4A1 1 0 013 5zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm1 4a1 1 0 100 2h12a1 1 0 100-2H4z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>

                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                                    {{ t('Admin Module') }}
                                </p>
                                <h1 class="truncate text-xl font-semibold text-slate-950">{{ translatedTitle }}</h1>
                                <p v-if="props.description" class="mt-1 truncate text-sm text-slate-600">
                                    {{ translatedDescription }}
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-3 text-sm">
                            <AdminGlobalSearch />

                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
                                :title="localeToggleLabel"
                                :aria-label="localeToggleLabel"
                                @click="toggleLocale"
                            >
                                <svg class="h-[18px] w-[18px]" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 3c3.866 0 7 3.134 7 7s-3.134 7-7 7-7-3.134-7-7 3.134-7 7-7Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.5 10h13" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 3c1.74 1.87 2.625 4.213 2.5 7-.125 2.787-.96 5.13-2.5 7" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 3c-1.74 1.87-2.625 4.213-2.5 7 .125 2.787.96 5.13 2.5 7" />
                                </svg>
                            </button>

                            <Link
                                :href="route('profile.edit')"
                                class="flex max-w-[12rem] items-center gap-2 rounded-xl px-2 py-1.5 transition hover:bg-slate-100 sm:max-w-none"
                                :title="displayName"
                            >
                                <span
                                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-950 text-xs font-semibold uppercase tracking-wide text-white"
                                    aria-hidden="true"
                                >
                                    {{ userInitials }}
                                </span>
                                <span class="truncate font-medium text-slate-900">
                                    {{ displayName }}
                                </span>
                            </Link>
                        </div>
                    </div>
                </header>

                <main id="main-content" class="flex-1 px-5 py-6 sm:px-8" tabindex="-1">
                    <AdminBreadcrumbs v-if="breadcrumbs.length" :items="breadcrumbs" />

                    <slot />
                </main>
            </div>
        </div>

        <AdminToastHost />
        <AdminConfirmDialog />
    </div>
</template>
