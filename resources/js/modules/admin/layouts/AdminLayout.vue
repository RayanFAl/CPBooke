<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AdminSidebar from '../components/AdminSidebar.vue';
import { useAdminLocale } from '../composables/useAdminLocale';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});

const page = usePage();
const sidebarOpen = ref(false);
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash ?? {});
const { locale, isArabic, setLocale, t, languageOptions } = useAdminLocale();
const translatedTitle = computed(() => t(props.title));
const translatedDescription = computed(() => t(props.description));
const activeLocaleIndex = computed(() => languageOptions.value.findIndex((option) => option.value === locale.value));
</script>

<template>
    <div class="admin-shell min-h-screen bg-slate-100 text-slate-900" :dir="isArabic ? 'rtl' : 'ltr'">
        <div class="flex min-h-screen">
            <div class="hidden w-72 shrink-0 lg:block">
                <AdminSidebar />
            </div>

            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden"
                @click="sidebarOpen = false"
            />

            <div
                class="fixed inset-y-0 left-0 z-50 w-72 transform transition lg:hidden"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <AdminSidebar />
            </div>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="border-b border-slate-200 bg-white/90 backdrop-blur">
                    <div class="flex items-center justify-between gap-4 px-5 py-4 sm:px-8">
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-700 lg:hidden"
                                @click="sidebarOpen = true"
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

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                                    {{ t('Admin Module') }}
                                </p>
                                <h1 class="text-xl font-semibold text-slate-950">{{ translatedTitle }}</h1>
                                <p v-if="props.description" class="mt-1 text-sm text-slate-600">
                                    {{ translatedDescription }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 text-sm">
                            <div class="relative rounded-[1.4rem] border border-slate-200 bg-white/95 p-1.5 shadow-[0_18px_40px_-30px_rgba(15,23,42,0.35)] backdrop-blur">
                                <div class="flex items-center gap-1.5" dir="ltr">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-[1rem] bg-slate-50 text-slate-500 ring-1 ring-slate-200">
                                        <svg class="h-[18px] w-[18px]" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.65">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 3c3.866 0 7 3.134 7 7s-3.134 7-7 7-7-3.134-7-7 3.134-7 7-7Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.5 10h13" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 3c1.74 1.87 2.625 4.213 2.5 7-.125 2.787-.96 5.13-2.5 7" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 3c-1.74 1.87-2.625 4.213-2.5 7 .125 2.787.96 5.13 2.5 7" />
                                        </svg>
                                    </div>

                                    <div class="relative grid grid-cols-2 rounded-[1rem] bg-slate-50 p-1 ring-1 ring-slate-200">
                                        <div
                                            class="pointer-events-none absolute inset-y-1 left-1 w-[calc(50%-0.25rem)] rounded-[0.8rem] bg-slate-950 shadow-[0_12px_24px_-18px_rgba(15,23,42,0.9)] transition-transform duration-300"
                                            :style="{ transform: `translateX(${Math.max(activeLocaleIndex, 0) * 100}%)` }"
                                        />

                                        <button
                                            v-for="option in languageOptions"
                                            :key="option.value"
                                            type="button"
                                            class="relative z-10 inline-flex min-w-[88px] items-center justify-center rounded-[0.8rem] px-4 py-2.5 text-xs font-semibold transition duration-200 sm:min-w-[108px]"
                                            :class="option.value === locale ? 'text-white' : 'text-slate-500 hover:text-slate-900'"
                                            @click="setLocale(option.value)"
                                        >
                                            <span class="truncate uppercase tracking-[0.22em]">
                                                {{ option.label }}
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="hidden text-right sm:block">
                                <p class="font-medium text-slate-900">{{ user.full_name ?? user.name }}</p>
                                <p class="text-slate-500">{{ user.email }}</p>
                            </div>
                            <Link
                                :href="route('profile.edit')"
                                class="rounded-xl border border-slate-200 px-4 py-2 font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                {{ t('Profile') }}
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="rounded-xl bg-slate-950 px-4 py-2 font-medium text-white transition hover:bg-slate-800"
                            >
                                {{ t('Logout') }}
                            </Link>
                        </div>
                    </div>
                </header>

                <main class="flex-1 px-5 py-6 sm:px-8">
                    <div v-if="flash.success || flash.error" class="mb-6 space-y-3">
                        <div
                            v-if="flash.success"
                            class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
                        >
                            {{ flash.success }}
                        </div>
                        <div
                            v-if="flash.error"
                            class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
                        >
                            {{ flash.error }}
                        </div>
                    </div>

                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>