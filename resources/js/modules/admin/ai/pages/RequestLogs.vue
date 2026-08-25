<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, required: true },
    sources: { type: Array, default: () => [] },
    modes: { type: Array, default: () => [] },
});

const { t, paginationLabel } = useAdminLocale();

const form = reactive({
    search: props.filters.search || '',
    source: props.filters.source || '',
    mode: props.filters.mode || '',
    intent: props.filters.intent || '',
    fallback: props.filters.fallback || '',
    success: props.filters.success || '',
});

const applyFilters = () => {
    router.get(route('admin.ai.logs.index'), {
        ...(form.search ? { search: form.search } : {}),
        ...(form.source ? { source: form.source } : {}),
        ...(form.mode ? { mode: form.mode } : {}),
        ...(form.intent ? { intent: form.intent } : {}),
        ...(form.fallback !== '' ? { fallback: form.fallback } : {}),
        ...(form.success !== '' ? { success: form.success } : {}),
    }, { preserveState: true, replace: true });
};

const formatTime = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};

const badgeClass = (source) => {
    if (source === 'gemini' || source === 'gemini_or_local') {
        return 'bg-cyan-100 text-cyan-800';
    }
    if (source === 'rules_hint') {
        return 'bg-slate-200 text-slate-700';
    }
    return 'bg-amber-100 text-amber-800';
};

const slotsText = (summary) => {
    if (!summary || typeof summary !== 'object') {
        return '—';
    }

    return Object.entries(summary)
        .filter(([, value]) => value !== null && value !== '' && value !== undefined)
        .map(([key, value]) => `${key}: ${value}`)
        .join(' · ') || '—';
};
</script>

<template>
    <AdminLayout
        title="AI Request Logs"
        description="Voice assistant and Gemini travel-assistant requests (message truncated, no API keys)."
    >
        <Head :title="t('AI Request Logs')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Platform') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('AI Request Logs') }}</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            {{ t('User messages sent to /api/v1/ai/travel-assistant. Conversation and offers payloads are not stored.') }}
                        </p>
                    </div>
                    <Link
                        :href="route('admin.ai.settings.index')"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        {{ t('AI settings') }}
                    </Link>
                </div>
            </div>

            <form class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-6" @submit.prevent="applyFilters">
                <input
                    v-model="form.search"
                    type="search"
                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm md:col-span-2"
                    :placeholder="t('Search message, intent, fallback reason')"
                >
                <select v-model="form.mode" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="">{{ t('All modes') }}</option>
                    <option v-for="mode in modes" :key="mode" :value="mode">{{ mode }}</option>
                </select>
                <select v-model="form.source" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="">{{ t('All sources') }}</option>
                    <option v-for="source in sources" :key="source" :value="source">{{ source }}</option>
                </select>
                <input
                    v-model="form.intent"
                    type="text"
                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                    :placeholder="t('Intent')"
                >
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    {{ t('Filter') }}
                </button>
            </form>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('When') }}</th>
                            <th class="px-4 py-3">{{ t('Message') }}</th>
                            <th class="px-4 py-3">{{ t('Intent') }}</th>
                            <th class="px-4 py-3">{{ t('Source') }}</th>
                            <th class="px-4 py-3">{{ t('Slots') }}</th>
                            <th class="px-4 py-3">{{ t('Latency') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="log in logs.data" :key="log.id" class="align-top hover:bg-slate-50/70">
                            <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-500">
                                {{ formatTime(log.created_at) }}
                                <div v-if="log.user" class="mt-1 text-slate-700">{{ log.user.name }}</div>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <p class="font-medium text-slate-900">{{ log.message || '—' }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ log.mode }}
                                    <span v-if="log.fallback"> · fallback</span>
                                    <span v-if="log.fallback_reason"> · {{ log.fallback_reason }}</span>
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <p>{{ log.intent || '—' }}</p>
                                <p class="text-xs text-slate-500">{{ log.product || '—' }}</p>
                                <p v-if="log.confidence != null" class="text-xs text-slate-500">conf {{ log.confidence }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="badgeClass(log.source)">
                                    {{ log.source || '—' }}
                                </span>
                                <p class="mt-1 text-xs text-slate-500">{{ log.model || '—' }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600 max-w-sm">
                                {{ slotsText(log.slots_summary) }}
                                <p v-if="log.missing_slots?.length" class="mt-1 text-amber-700">
                                    missing: {{ log.missing_slots.join(', ') }}
                                </p>
                                <p v-if="log.recommendations_count != null" class="mt-1">
                                    recs: {{ log.recommendations_count }}
                                </p>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span :class="log.success ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ log.success ? 'OK' : 'Fail' }}
                                </span>
                                <p class="text-xs text-slate-500">{{ log.latency_ms }} ms</p>
                            </td>
                        </tr>
                        <tr v-if="!logs.data?.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                                {{ t('No AI requests logged yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="logs.links?.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in logs.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-lg px-3 py-1 text-sm"
                    :class="link.active ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700'"
                    v-html="paginationLabel(link.label)"
                />
            </div>
        </section>
    </AdminLayout>
</template>
