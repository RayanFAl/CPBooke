<script setup>
import { computed, ref } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import TemplateManager from '../components/TemplateManager.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
});

const { t, isArabic } = useAdminLocale();
const page = usePage();

const allowedTabs = ['overview', 'channels', 'logs', 'templates', 'tools'];
const requestedTab = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search).get('tab');
const activeTab = ref(allowedTabs.includes(requestedTab) ? requestedTab : 'overview');

const flashSuccess = computed(() => page.props.flash?.success ?? '');
const flashError = computed(() => page.props.flash?.error ?? '');

const tabs = computed(() => [
    { id: 'overview', label: 'Overview' },
    { id: 'channels', label: 'Channels' },
    { id: 'logs', label: 'Logs' },
    { id: 'templates', label: 'Templates', count: props.dashboard.templates?.length ?? 0 },
    { id: 'tools', label: 'Tools' },
]);

const pushTargets = computed(() => props.dashboard.push_targets ?? []);
const testTargets = computed(() => {
    const targets = props.dashboard.test_targets ?? [];

    return targets.length > 0 ? targets : pushTargets.value;
});
const templates = computed(() => props.dashboard.templates ?? []);
const templateCategories = computed(() => props.dashboard.template_categories ?? []);
const availableChannels = computed(() => props.dashboard.available_channels ?? ['in_app', 'email', 'push', 'sms', 'whatsapp']);
const activeTemplates = computed(() => templates.value.filter((template) => template.is_active));

const pushForm = useForm({
    user_id: pushTargets.value[0]?.id ?? '',
    title: 'CPBooke News',
    body: 'مرحباً! هذا إشعار تجريبي من CPBooke.',
});

const templateTestForm = useForm({
    user_id: testTargets.value[0]?.id ?? '',
    template_code: 'ALL',
    include_email: false,
});

const selectedTemplate = computed(() => {
    if (templateTestForm.template_code === 'ALL') {
        return null;
    }

    return templates.value.find((template) => template.code === templateTestForm.template_code) ?? null;
});

const sendTestPush = () => {
    pushForm.post(route('admin.notifications.push-test'), {
        preserveScroll: true,
    });
};

const sendTestTemplate = () => {
    if (templateTestForm.template_code === 'ALL') {
        const confirmed = window.confirm(
            t('This will send every active template to the selected user. Continue?'),
        );

        if (!confirmed) {
            return;
        }
    }

    templateTestForm.post(route('admin.notifications.template-test'), {
        preserveScroll: true,
    });
};

const retryLog = (id) => {
    useForm({}).post(route('admin.notifications.retry', id), {
        preserveScroll: true,
    });
};

const pretty = (value) => {
    if (!value) {
        return t('Not available');
    }

    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

const staffLabel = (template) => {
    if (!template) return '';

    return isArabic.value
        ? (template.label_ar || template.name || template.code)
        : (template.label || template.name || template.code);
};

const logLabel = (log) => {
    if (!log) return t('Unknown');

    return isArabic.value
        ? (log.template_label_ar || log.template_code)
        : (log.template_label || log.template_code);
};

const categoryLabel = (template) => {
    if (!template) return '';

    return isArabic.value
        ? (template.category_label_ar || template.category_label || '')
        : (template.category_label || '');
};
</script>

<template>
    <AdminLayout
        title="Notifications"
        description="Delivery health, logs, templates, and push tools for the notification engine."
    >
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Engagement') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Notifications') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {{ t('Manage push delivery, monitor channels, review logs, and edit bilingual templates from one workspace.') }}
                </p>
                <p v-if="flashSuccess" class="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ flashSuccess }}
                </p>
                <p v-if="flashError" class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ flashError }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-2xl px-4 py-2 text-sm font-medium transition"
                    :class="activeTab === tab.id ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    @click="activeTab = tab.id"
                >
                    <span>{{ t(tab.label) }}</span>
                    <span
                        v-if="tab.count !== undefined"
                        class="rounded-full px-2 py-0.5 text-xs"
                        :class="activeTab === tab.id ? 'bg-white/15 text-white' : 'bg-white text-slate-500'"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <!-- Overview -->
            <div v-show="activeTab === 'overview'" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <article
                        v-for="metric in [
                            { key: 'total_logs', label: 'Total logs' },
                            { key: 'pending_logs', label: 'Pending' },
                            { key: 'sent_logs', label: 'Sent' },
                            { key: 'failed_logs', label: 'Failed' },
                            { key: 'unread_in_app', label: 'Unread in-app' },
                        ]"
                        :key="metric.key"
                        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">{{ t(metric.label) }}</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-950">{{ dashboard.metrics[metric.key] }}</p>
                    </article>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Quick actions') }}</h3>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="activeTab = 'templates'"
                            >
                                {{ t('Edit templates') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="activeTab = 'logs'"
                            >
                                {{ t('View delivery logs') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="activeTab = 'channels'"
                            >
                                {{ t('Check channel status') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="activeTab = 'tools'"
                            >
                                {{ t('Send test push') }}
                            </button>
                        </div>
                    </article>

                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Failed deliveries') }}</h3>
                        <div v-if="dashboard.failed_logs.length === 0" class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-600">
                            {{ t('No failed notification deliveries were recorded.') }}
                        </div>
                        <div v-else class="mt-4 space-y-2">
                            <div
                                v-for="log in dashboard.failed_logs.slice(0, 3)"
                                :key="log.id"
                                class="rounded-2xl bg-slate-50 px-4 py-3 text-sm"
                            >
                                <p class="font-medium text-slate-950">{{ logLabel(log) }} · {{ pretty(log.channel) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ log.user.name || log.user.email || t('Unknown user') }}</p>
                            </div>
                            <button
                                type="button"
                                class="mt-2 text-sm font-medium text-cyan-700 hover:text-cyan-800"
                                @click="activeTab = 'logs'"
                            >
                                {{ t('View all failed deliveries') }} →
                            </button>
                        </div>
                    </article>
                </div>
            </div>

            <!-- Channels -->
            <div v-show="activeTab === 'channels'" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-950">{{ t('Channel status monitoring') }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ t('Provider configuration for each delivery channel.') }}</p>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="channel in dashboard.channel_statuses"
                        :key="channel.channel"
                        class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-4"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-950">{{ pretty(channel.channel) }}</p>
                                <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ channel.provider }}</p>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]"
                                :class="channel.configured ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                            >
                                {{ channel.configured ? t('Configured') : t('Fallback') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs -->
            <div v-show="activeTab === 'logs'" class="space-y-4">
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Failed deliveries') }}</h3>
                    <div v-if="dashboard.failed_logs.length === 0" class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-600">
                        {{ t('No failed notification deliveries were recorded.') }}
                    </div>
                    <div v-else class="mt-4 space-y-3">
                        <div v-for="log in dashboard.failed_logs" :key="log.id" class="rounded-2xl border border-slate-200 px-4 py-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-950">{{ logLabel(log) }} · {{ pretty(log.channel) }}</p>
                                    <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ log.user.name || log.user.email || t('Unknown user') }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                                    @click="retryLog(log.id)"
                                >
                                    {{ t('Retry') }}
                                </button>
                            </div>
                            <p class="mt-3 text-sm text-slate-600">{{ log.response_payload.error || t('Unknown failure') }}</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Notification logs') }}</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                            <thead class="bg-slate-50 text-xs uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('User') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Template') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Channel') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Status') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Retries') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Created') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="log in dashboard.logs" :key="log.id">
                                    <td class="px-4 py-3">{{ log.user.name || log.user.email || t('Unknown user') }}</td>
                                    <td class="px-4 py-3">
                                        <p>{{ logLabel(log) }}</p>
                                        <p class="mt-0.5 font-mono text-[11px] text-slate-400">{{ log.template_code }}</p>
                                    </td>
                                    <td class="px-4 py-3">{{ pretty(log.channel) }}</td>
                                    <td class="px-4 py-3">{{ pretty(log.status) }}</td>
                                    <td class="px-4 py-3">{{ log.retry_count }}</td>
                                    <td class="px-4 py-3">{{ log.created_at }}</td>
                                </tr>
                                <tr v-if="dashboard.logs.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-500">{{ t('No notification logs yet.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>

            <!-- Templates -->
            <div v-show="activeTab === 'templates'">
                <TemplateManager
                    :templates="dashboard.templates ?? []"
                    :template-categories="templateCategories"
                    :available-channels="availableChannels"
                />
            </div>

            <!-- Tools -->
            <div v-show="activeTab === 'tools'" class="space-y-4">
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">{{ t('Tester') }}</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-950">{{ t('Test existing notifications') }}</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ t('Send any current template (or all of them) to a user. In-app inbox is always written; push is sent when the user has a device.') }}
                            </p>
                        </div>
                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                            {{ activeTemplates.length }} {{ t('active templates') }}
                        </span>
                    </div>

                    <form class="mt-5 grid gap-4 lg:grid-cols-[1.2fr_1.4fr_auto]" @submit.prevent="sendTestTemplate">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Recipient') }}</span>
                            <select
                                v-model="templateTestForm.user_id"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                required
                            >
                                <option disabled value="">{{ t('Select a user') }}</option>
                                <option
                                    v-for="target in testTargets"
                                    :key="target.id"
                                    :value="target.id"
                                >
                                    #{{ target.id }} · {{ target.name || target.email }}
                                    <template v-if="target.devices"> · {{ target.devices }} {{ t('devices') }}</template>
                                </option>
                            </select>
                            <p v-if="templateTestForm.errors.user_id" class="mt-1 text-xs text-rose-600">{{ templateTestForm.errors.user_id }}</p>
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Notification') }}</span>
                            <select
                                v-model="templateTestForm.template_code"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                required
                            >
                                <option value="ALL">{{ t('All active templates') }} ({{ activeTemplates.length }})</option>
                                <option
                                    v-for="template in activeTemplates"
                                    :key="template.code"
                                    :value="template.code"
                                >
                                    {{ staffLabel(template) }}
                                </option>
                            </select>
                            <p v-if="templateTestForm.errors.template_code" class="mt-1 text-xs text-rose-600">{{ templateTestForm.errors.template_code }}</p>
                        </label>

                        <div class="flex items-end">
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="templateTestForm.processing || testTargets.length === 0 || activeTemplates.length === 0"
                            >
                                {{ templateTestForm.processing ? t('Sending...') : (templateTestForm.template_code === 'ALL' ? t('Send all') : t('Send this notification')) }}
                            </button>
                        </div>
                    </form>

                    <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="templateTestForm.include_email" type="checkbox" class="rounded border-slate-300">
                        {{ t('Also send email') }}
                    </label>

                    <div v-if="selectedTemplate" class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-700">
                        <p class="font-medium text-slate-950">{{ staffLabel(selectedTemplate) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ categoryLabel(selectedTemplate) }} · {{ selectedTemplate.code }}</p>
                        <p class="mt-3">{{ selectedTemplate.translations?.ar?.subject || selectedTemplate.subject }}</p>
                        <p class="mt-1 text-slate-600">{{ selectedTemplate.translations?.ar?.body || selectedTemplate.body }}</p>
                    </div>

                    <p v-if="activeTemplates.length === 0" class="mt-3 text-sm text-amber-800">
                        {{ t('No templates yet. Open the Templates tab and click Sync templates first.') }}
                    </p>
                </article>

                <article class="rounded-3xl border border-amber-200 bg-amber-50/70 p-6 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-700">{{ t('Temporary') }}</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-950">{{ t('Custom push') }}</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ t('Send a free-text FCM push, not tied to a catalog template.') }}
                            </p>
                        </div>
                    </div>

                    <form class="mt-4 grid gap-4 lg:grid-cols-[1.2fr_1fr_1fr_auto]" @submit.prevent="sendTestPush">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Recipient') }}</span>
                            <select
                                v-model="pushForm.user_id"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                required
                            >
                                <option disabled value="">{{ t('Select a user with a device') }}</option>
                                <option
                                    v-for="target in pushTargets"
                                    :key="target.id"
                                    :value="target.id"
                                >
                                    #{{ target.id }} · {{ target.name || target.email }} · {{ target.devices }} {{ t('devices') }}
                                </option>
                            </select>
                            <p v-if="pushForm.errors.user_id" class="mt-1 text-xs text-rose-600">{{ pushForm.errors.user_id }}</p>
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Title') }}</span>
                            <input
                                v-model="pushForm.title"
                                type="text"
                                maxlength="120"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                required
                            >
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Body') }}</span>
                            <input
                                v-model="pushForm.body"
                                type="text"
                                maxlength="500"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                required
                            >
                        </label>

                        <div class="flex items-end">
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="pushForm.processing || pushTargets.length === 0"
                            >
                                {{ pushForm.processing ? t('Sending...') : t('Send push') }}
                            </button>
                        </div>
                    </form>

                    <p v-if="pushTargets.length === 0" class="mt-3 text-sm text-amber-800">
                        {{ t('No users with active push devices yet. Open the mobile app and register a device token first.') }}
                    </p>
                </article>
            </div>
        </section>
    </AdminLayout>
</template>
