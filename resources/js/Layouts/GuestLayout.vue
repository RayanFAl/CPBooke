<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../modules/admin/composables/useAdminLocale';

const page = usePage();
const { locale, isArabic, setLocale, t } = useAdminLocale();

const companyName = computed(() => {
    const name = String(page.props.platform?.company_name ?? '').trim();

    if (!name || ['Booke', 'بوكي', 'CPBooke', 'Laravel'].includes(name)) {
        return 'BookNow';
    }

    return name;
});

const toggleLocale = () => {
    setLocale(locale.value === 'ar' ? 'en' : 'ar');
};

const localeToggleLabel = computed(() => (
    locale.value === 'ar' ? t('Switch to English') : t('Switch to Arabic')
));

const highlights = computed(() => [
    {
        title: t('Operations overview'),
        text: t('Track bookings, providers, and live system health from one place.'),
    },
    {
        title: t('Finance & wallets'),
        text: t('Monitor customer and provider balances with full transaction history.'),
    },
    {
        title: t('Secure staff access'),
        text: t('Role-based permissions for every Control Panel action.'),
    },
]);
</script>

<template>
    <div
        class="guest-shell relative min-h-screen overflow-hidden bg-slate-950 text-slate-900"
        :dir="isArabic ? 'rtl' : 'ltr'"
    >
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-24 top-[-10%] h-[38rem] w-[38rem] rounded-full bg-[#2f6db8]/30 blur-3xl" />
            <div class="absolute bottom-[-20%] right-[-10%] h-[32rem] w-[32rem] rounded-full bg-[#f0b429]/15 blur-3xl" />
            <div
                class="absolute inset-0 opacity-[0.12]"
                style="background-image: linear-gradient(rgba(255,255,255,.08) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px); background-size: 48px 48px;"
            />
        </div>

        <div class="relative mx-auto grid min-h-screen max-w-7xl lg:grid-cols-2">
            <section class="hidden flex-col justify-between px-10 py-10 text-white lg:flex xl:px-14">
                <Link href="/" class="inline-flex items-center gap-4">
                    <ApplicationLogo class="h-14 w-14 rounded-[1.15rem] shadow-lg shadow-slate-950/40" />
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.32em] text-[#8ec5ff]">
                            {{ companyName }}
                        </p>
                        <p class="mt-1 text-lg font-semibold tracking-tight">{{ t('Control Panel') }}</p>
                    </div>
                </Link>

                <div class="max-w-lg space-y-8">
                    <div class="space-y-4">
                        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-[#f0b429]">
                            {{ t('Operations console') }}
                        </p>
                        <h1 class="text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">
                            {{ t('Run BookNow from a single professional dashboard.') }}
                        </h1>
                        <p class="max-w-md text-base leading-7 text-slate-300">
                            {{ t('Sign in to manage bookings, finance, providers, and support with the same clarity your team expects in production.') }}
                        </p>
                    </div>

                    <ul class="space-y-4">
                        <li
                            v-for="item in highlights"
                            :key="item.title"
                            class="guest-highlight rounded-2xl border border-white/10 bg-white/5 px-5 py-4 backdrop-blur-sm"
                        >
                            <p class="text-sm font-semibold text-white">{{ item.title }}</p>
                            <p class="mt-1 text-sm leading-6 text-slate-300">{{ item.text }}</p>
                        </li>
                    </ul>
                </div>

                <p class="text-sm text-slate-400">
                    {{ t('Authorized staff only. All admin actions are audited.') }}
                </p>
            </section>

            <section class="flex flex-col px-5 py-6 sm:px-8 lg:items-center lg:justify-center lg:px-10">
                <div class="mb-6 flex items-center justify-between lg:mb-8 lg:w-full lg:max-w-md">
                    <Link href="/" class="inline-flex items-center gap-3 lg:hidden">
                        <ApplicationLogo class="h-11 w-11 rounded-2xl shadow-sm" />
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#8ec5ff]">
                                {{ companyName }}
                            </p>
                            <p class="text-sm font-medium text-slate-200">{{ t('Control Panel') }}</p>
                        </div>
                    </Link>
                    <div class="hidden lg:block" />

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-slate-200 transition hover:bg-white/10 hover:text-white"
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
                </div>

                <div class="guest-panel w-full max-w-md rounded-[1.75rem] border border-white/10 bg-white p-6 shadow-2xl shadow-slate-950/40 sm:p-8">
                    <slot />
                </div>

                <p class="mt-6 text-center text-xs text-slate-400 lg:w-full lg:max-w-md">
                    {{ isArabic
                        ? 'جميع الحقوق محفوظة · مجموعة مدين · 2026'
                        : 'All rights reserved · Median Group · 2026' }}
                </p>
            </section>
        </div>
    </div>
</template>

<style scoped>
.guest-shell {
    animation: guest-fade 480ms ease-out;
}

.guest-panel {
    animation: guest-rise 560ms cubic-bezier(0.22, 1, 0.36, 1);
}

.guest-highlight {
    transition: transform 220ms ease, background-color 220ms ease, border-color 220ms ease;
}

.guest-highlight:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.18);
    background-color: rgba(255, 255, 255, 0.08);
}

@keyframes guest-fade {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes guest-rise {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
