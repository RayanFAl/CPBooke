<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AdminSidebar from '../components/AdminSidebar.vue';

defineProps({
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
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-900">
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
                                <span class="sr-only">Open navigation</span>
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
                                    Admin Module
                                </p>
                                <h1 class="text-xl font-semibold text-slate-950">{{ title }}</h1>
                                <p v-if="description" class="mt-1 text-sm text-slate-600">
                                    {{ description }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 text-sm">
                            <div class="hidden text-right sm:block">
                                <p class="font-medium text-slate-900">{{ user.full_name ?? user.name }}</p>
                                <p class="text-slate-500">{{ user.email }}</p>
                            </div>
                            <Link
                                :href="route('profile.edit')"
                                class="rounded-xl border border-slate-200 px-4 py-2 font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Profile
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="rounded-xl bg-slate-950 px-4 py-2 font-medium text-white transition hover:bg-slate-800"
                            >
                                Logout
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