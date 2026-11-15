<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../modules/admin/composables/useAdminLocale';

const page = usePage();
const { locale, isArabic, setLocale, t } = useAdminLocale();

const companyName = computed(() => {
    const name = String(page.props.platform?.company_name ?? '').trim();

    if (!name || ['BookNow', 'Booke', 'بوكي', 'CPBooke', 'Laravel'].includes(name)) {
        return 'Booke';
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
    t('Operations overview'),
    t('Finance & wallets'),
    t('Secure staff access'),
]);
</script>

<template>
    <div
        class="guest-shell relative min-h-screen overflow-hidden bg-slate-950 text-slate-900"
        :dir="isArabic ? 'rtl' : 'ltr'"
    >
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -start-28 top-[-12%] h-[40rem] w-[40rem] rounded-full bg-[#2f6db8]/28 blur-3xl" />
            <div class="absolute bottom-[-18%] -end-20 h-[34rem] w-[34rem] rounded-full bg-[#f0b429]/14 blur-3xl" />
            <div
                class="absolute inset-0 opacity-[0.1]"
                style="background-image: linear-gradient(rgba(255,255,255,.09) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.09) 1px, transparent 1px); background-size: 44px 44px;"
            />
        </div>

        <div class="relative mx-auto grid min-h-screen max-w-6xl lg:grid-cols-2">
            <section class="hidden flex-col justify-between px-10 py-11 text-white lg:flex xl:px-14">
                <Link href="/" class="inline-flex items-center gap-3.5">
                    <ApplicationLogo class="h-12 w-12 rounded-2xl shadow-lg shadow-slate-950/40" />
                    <div>
                        <p class="text-xl font-semibold tracking-tight">{{ companyName }}</p>
                        <p class="mt-0.5 text-sm text-[#8ec5ff]">{{ t('Control Panel') }}</p>
                    </div>
                </Link>

                <div class="max-w-md space-y-7">
                    <div class="space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#f0b429]">
                            {{ t('Operations console') }}
                        </p>
                        <h1 class="text-4xl font-semibold leading-[1.15] tracking-tight xl:text-[2.75rem]">
                            {{ t('Run Booke from a single professional dashboard.') }}
                        </h1>
                    </div>

                    <ul class="space-y-3">
                        <li
                            v-for="item in highlights"
                            :key="item"
                            class="guest-highlight flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.06] px-4 py-3.5 text-sm font-medium text-slate-100"
                        >
                            <span class="h-2 w-2 shrink-0 rounded-full bg-[#2f6db8]" />
                            {{ item }}
                        </li>
                    </ul>
                </div>

                <p class="text-sm text-slate-400">
                    {{ t('Authorized staff only. All admin actions are audited.') }}
                </p>
            </section>

            <section class="flex flex-col px-5 py-6 sm:px-8 lg:items-center lg:justify-center lg:px-10">
                <div class="mb-7 flex items-center justify-between lg:mb-8 lg:w-full lg:max-w-[26rem]">
                    <Link href="/" class="inline-flex items-center gap-3 lg:hidden">
                        <ApplicationLogo class="h-11 w-11 rounded-2xl shadow-sm" />
                        <div>
                            <p class="font-semibold text-white">{{ companyName }}</p>
                            <p class="text-xs text-[#8ec5ff]">{{ t('Control Panel') }}</p>
                        </div>
                    </Link>
                    <div class="hidden lg:block" />

                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 text-xs font-semibold text-slate-200 transition hover:bg-white/10 hover:text-white"
                        :title="localeToggleLabel"
                        :aria-label="localeToggleLabel"
                        @click="toggleLocale"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 3c3.866 0 7 3.134 7 7s-3.134 7-7 7-7-3.134-7-7 3.134-7 7-7Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.5 10h13" />
                        </svg>
                        {{ locale === 'ar' ? 'EN' : 'ع' }}
                    </button>
                </div>

                <div class="guest-panel w-full max-w-[26rem] rounded-[1.5rem] border border-white/10 bg-white p-7 shadow-2xl shadow-slate-950/45 sm:p-8">
                    <slot />
                </div>

                <p class="mt-6 text-center text-xs text-slate-500 lg:w-full lg:max-w-[26rem]">
                    {{ isArabic
                        ? 'مجموعة مدين · 2026'
                        : 'Median Group · 2026' }}
                </p>
            </section>
        </div>
    </div>
</template>

<style scoped>
.guest-shell {
    animation: guest-fade 420ms ease-out;
}

.guest-panel {
    animation: guest-rise 500ms cubic-bezier(0.22, 1, 0.36, 1);
}

.guest-highlight {
    transition: background-color 200ms ease, border-color 200ms ease;
}

.guest-highlight:hover {
    border-color: rgba(255, 255, 255, 0.18);
    background-color: rgba(255, 255, 255, 0.09);
}

@keyframes guest-fade {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes guest-rise {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
