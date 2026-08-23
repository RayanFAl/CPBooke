<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminEmptyState from '../../components/AdminEmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, required: true },
    modules: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    entity_types: { type: Array, default: () => [] },
});

const { t } = useAdminLocale();

const form = reactive({
    search: props.filters.search || '',
    module: props.filters.module || '',
    status: props.filters.status || '',
    entity_type: props.filters.entity_type || '',
    action: props.filters.action || '',
});

const applyFilters = () => {
    router.get(route('admin.audit.index'), {
        ...(form.search ? { search: form.search } : {}),
        ...(form.module ? { module: form.module } : {}),
        ...(form.status ? { status: form.status } : {}),
        ...(form.entity_type ? { entity_type: form.entity_type } : {}),
        ...(form.action ? { action: form.action } : {}),
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

const summarizeValues = (values) => {
    if (!values || typeof values !== 'object') {
        return '—';
    }

    return Object.entries(values)
        .map(([key, value]) => `${key}: ${value ?? '—'}`)
        .join(' · ');
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Audit Center')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Platform') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Audit Center') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {{ t('Who changed what, when, from where — with old and new values for support, finance, and management.') }}
                </p>
            </div>

            <form class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-6" @submit.prevent="applyFilters">
                <input
                    v-model="form.search"
                    type="search"
                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm md:col-span-2"
                    :placeholder="t('Search subject, action, entity id')"
                >
                <select v-model="form.module" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="">{{ t('All modules') }}</option>
                    <option v-for="module in modules" :key="module" :value="module">{{ module }}</option>
                </select>
                <select v-model="form.entity_type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="">{{ t('All entities') }}</option>
                    <option v-for="entity in entity_types" :key="entity" :value="entity">{{ entity }}</option>
                </select>
                <select v-model="form.status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="">{{ t('All statuses') }}</option>
                    <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                </select>
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    {{ t('Filter') }}
                </button>
            </form>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('When') }}</th>
                            <th class="px-4 py-3">{{ t('Actor') }}</th>
                            <th class="px-4 py-3">{{ t('Action') }}</th>
                            <th class="px-4 py-3">{{ t('Entity') }}</th>
                            <th class="px-4 py-3">{{ t('Result') }}</th>
                            <th class="px-4 py-3">{{ t('Changes') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="log in logs.data" :key="log.id" class="align-top hover:bg-slate-50/80">
                            <td class="px-4 py-3 text-slate-600">
                                <div>{{ formatTime(log.created_at) }}</div>
                                <div class="mt-1 text-xs text-slate-400">{{ log.ip_address || '—' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ log.actor?.name || t('System') }}</div>
                                <div class="text-xs text-slate-500">{{ log.actor?.email || '—' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ log.subject }}</div>
                                <div class="text-xs text-slate-500">{{ log.module }} · {{ log.action }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    v-if="log.entity_url"
                                    :href="log.entity_url"
                                    class="font-medium text-cyan-700 hover:text-cyan-800"
                                >
                                    {{ log.entity_type }} #{{ log.entity_id }}
                                </Link>
                                <span v-else class="text-slate-500">{{ log.entity_type ? `${log.entity_type} #${log.entity_id}` : '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-lg px-2 py-1 text-xs font-semibold"
                                    :class="log.status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'"
                                >
                                    {{ log.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                <div><span class="font-medium">{{ t('Old') }}:</span> {{ summarizeValues(log.old_values) }}</div>
                                <div class="mt-1"><span class="font-medium">{{ t('New') }}:</span> {{ summarizeValues(log.new_values) }}</div>
                            </td>
                        </tr>
                        <tr v-if="!logs.data?.length">
                            <td colspan="6" class="px-4 py-6">
                                <AdminEmptyState
                                    title="No audit events found for the current filters."
                                    description="Try broadening the filters to see more audit activity."
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AdminLayout>
</template>
