<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    ticketId: {
        type: Number,
        required: true,
    },
    report: {
        type: Object,
        default: null,
    },
    resolutionTypeOptions: {
        type: Array,
        required: true,
    },
    resolutionStatusOptions: {
        type: Array,
        required: true,
    },
});

const { t } = useAdminLocale();

const buildFormState = (report) => ({
    resolution_type: report?.resolution_type || props.resolutionTypeOptions[0]?.name || 'resolved',
    root_cause: report?.root_cause || '',
    actions_taken: report?.actions_taken || '',
    resolution_summary: report?.resolution_summary || '',
    internal_notes: report?.internal_notes || '',
    customer_visible_notes: report?.customer_visible_notes || '',
    status_after: report?.status_after || props.resolutionStatusOptions[0]?.name || 'resolved',
    escalated: Boolean(report?.escalated),
    satisfaction_requested: Boolean(report?.satisfaction_requested),
    metadata: report?.metadata || {},
});

const form = useForm(buildFormState(props.report));

watch(() => props.report, (report) => {
    form.defaults(buildFormState(report));
    form.reset();
    form.clearErrors();
}, { deep: true });

const submit = () => {
    form.post(route('admin.support.resolution-report.upsert', props.ticketId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <form class="space-y-5 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-950">{{ t('Resolution Report Form') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ t('Capture the real cause, final actions, and ticket outcome before marking the case complete.') }}</p>
            </div>
            <span class="inline-flex rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">
                {{ report ? t('Update') : t('Required For Close') }}
            </span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-700">{{ t('Resolution Type') }}</label>
                <select v-model="form.resolution_type" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600">
                    <option v-for="option in resolutionTypeOptions" :key="option.name" :value="option.name">{{ t(option.label) }}</option>
                </select>
                <p v-if="form.errors.resolution_type" class="mt-2 text-sm text-rose-600">{{ form.errors.resolution_type }}</p>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">{{ t('Status After') }}</label>
                <select v-model="form.status_after" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600">
                    <option v-for="option in resolutionStatusOptions" :key="option.name" :value="option.name">{{ t(option.label) }}</option>
                </select>
                <p v-if="form.errors.status_after" class="mt-2 text-sm text-rose-600">{{ form.errors.status_after }}</p>
            </div>
        </div>

        <div>
            <label class="text-sm font-medium text-slate-700">{{ t('Root Cause') }}</label>
            <textarea v-model="form.root_cause" rows="4" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600" />
            <p v-if="form.errors.root_cause" class="mt-2 text-sm text-rose-600">{{ form.errors.root_cause }}</p>
        </div>

        <div>
            <label class="text-sm font-medium text-slate-700">{{ t('Actions Taken') }}</label>
            <textarea v-model="form.actions_taken" rows="4" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600" />
            <p v-if="form.errors.actions_taken" class="mt-2 text-sm text-rose-600">{{ form.errors.actions_taken }}</p>
        </div>

        <div>
            <label class="text-sm font-medium text-slate-700">{{ t('Resolution Summary') }}</label>
            <textarea v-model="form.resolution_summary" rows="4" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600" />
            <p v-if="form.errors.resolution_summary" class="mt-2 text-sm text-rose-600">{{ form.errors.resolution_summary }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-700">{{ t('Internal Notes') }}</label>
                <textarea v-model="form.internal_notes" rows="4" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600" />
                <p v-if="form.errors.internal_notes" class="mt-2 text-sm text-rose-600">{{ form.errors.internal_notes }}</p>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">{{ t('Customer Visible Notes') }}</label>
                <textarea v-model="form.customer_visible_notes" rows="4" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600" />
                <p v-if="form.errors.customer_visible_notes" class="mt-2 text-sm text-rose-600">{{ form.errors.customer_visible_notes }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-6 rounded-2xl bg-slate-50 px-4 py-4">
            <label class="inline-flex items-center gap-3 text-sm text-slate-700">
                <input v-model="form.escalated" type="checkbox" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                <span>{{ t('Escalated') }}</span>
            </label>
            <label class="inline-flex items-center gap-3 text-sm text-slate-700">
                <input v-model="form.satisfaction_requested" type="checkbox" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                <span>{{ t('Satisfaction Requested') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end gap-3">
            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60" :disabled="form.processing">
                {{ report ? t('Update Resolution Report') : t('Save Resolution Report') }}
            </button>
        </div>
    </form>
</template>