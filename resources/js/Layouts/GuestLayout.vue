<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../modules/admin/composables/useAdminLocale';

const page = usePage();
const { locale, isArabic, setLocale, t } = useAdminLocale();

const companyName = computed(() => page.props.platform?.company_name || t('Booke'));

const toggleLocale = () => {
    setLocale(locale.value === 'ar' ? 'en' : 'ar');
};

const localeToggleLabel = computed(() => (
    locale.value === 'ar' ? t('Switch to English') : t('Switch to Arabic')
));
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-slate-100 text-slate-900"
        :dir="isArabic ? 'rtl' : 'ltr'"
    >
        <header class="flex items-center justify-between px-5 py-4 sm:px-8">
            <Link href="/" class="flex items-center gap-3">
                <ApplicationLogo class="h-10 w-10 fill-current text-slate-700" />
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                        {{ companyName }}
                    </p>
                    <p class="text-sm font-medium text-slate-600">{{ t('Control Panel') }}</p>
                </div>
            </Link>

            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 transition hover:bg-white hover:text-slate-900"
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
        </header>

        <main class="flex flex-1 items-center justify-center px-5 pb-10 sm:px-8">
            <div class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <slot />
            </div>
        </main>
    </div>
</template>
