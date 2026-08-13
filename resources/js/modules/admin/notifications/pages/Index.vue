<script setup>
import { computed, reactive, watch } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminModulePage from '../../components/AdminModulePage.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
});

const { t } = useAdminLocale();

const pushTargets = computed(() => props.dashboard.push_targets ?? []);
const availableChannels = computed(() => props.dashboard.available_channels ?? ['in_app', 'email', 'push', 'sms', 'whatsapp']);

const pushForm = useForm({
    user_id: pushTargets.value[0]?.id ?? '',
    title: 'CPBooke News',
    body: 'مرحباً! هذا إشعار تجريبي من CPBooke.',
});

const drafts = reactive({});

const hydrateDrafts = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);

    for (const template of props.dashboard.templates ?? []) {
        drafts[template.id] = {
            name: template.name ?? '',
            subject: template.subject ?? '',
            body: template.body ?? '',
            channels: [...(template.channels ?? [])],
            variables: [...(template.variables ?? [])],
            is_active: Boolean(template.is_active),
            saving: false,
        };
    }
};

hydrateDrafts();
watch(() => props.dashboard.templates, hydrateDrafts, { deep: true });

const sendTestPush = () => {
    pushForm.post(route('admin.notifications.push-test'), {
        preserveScroll: true,
    });
};

const retryLog = (id) => {
    useForm({}).post(route('admin.notifications.retry', id), {
        preserveScroll: true,
    });
};

const syncTemplates = () => {
    router.post(route('admin.notifications.templates.sync'), {}, {
        preserveScroll: true,
    });
};

const toggleChannel = (templateId, channel) => {
    const draft = drafts[templateId];
    if (!draft) {
        return;
    }

    if (draft.channels.includes(channel)) {
        draft.channels = draft.channels.filter((item) => item !== channel);
        return;
    }

    draft.channels = [...draft.channels, channel];
};

const updateTemplate = (template) => {
    const draft = drafts[template.id];
    if (!draft) {
        return;
    }

    draft.saving = true;

    useForm({
        name: draft.name,
        subject: draft.subject,
        body: draft.body,
        channels: draft.channels,
        variables: draft.variables,
        is_active: draft.is_active,
    }).put(route('admin.notifications.templates.update', template.id), {
        preserveScroll: true,
        onFinish: () => {
            draft.saving = false;
        },
    });
};

const pretty = (value) => {
    if (!value) {
        return t('Not available');
    }

    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

const formatVariables = (variables) => {
    if (!Array.isArray(variables) || variables.length === 0) {
        return '';
    }

    return variables.map((name) => `{${name}}`).join(' ');
};
</script>

<template>
    <AdminLayout title="Notifications" description="Observe delivery health, retry failed notifications, and manage message templates from one backbone console.">
        <AdminModulePage eyebrow="Engagement" title="Notifications" description="Centralized logs, templates, and channel visibility for the notification engine.">
            <section class="rounded-3xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-700">{{ t('Temporary') }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-950">{{ t('Push notification tester') }}</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ t('Send a real FCM push to a user with an active device. For local experiments only.') }}
                        </p>
                    </div>
                    <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-amber-800">
                        {{ t('Temp panel') }}
                    </span>
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
                        <p v-if="pushForm.errors.title" class="mt-1 text-xs text-rose-600">{{ pushForm.errors.title }}</p>
                    </label>

                    <label class="block lg:col-span-1">
                        <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Body') }}</span>
                        <input
                            v-model="pushForm.body"
                            type="text"
                            maxlength="500"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                            required
                        >
                        <p v-if="pushForm.errors.body" class="mt-1 text-xs text-rose-600">{{ pushForm.errors.body }}</p>
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
            </section>

            <div class="mt-4 grid gap-4 md:grid-cols-5">
                <article v-for="metric in [
                    { key: 'total_logs', label: 'Total logs' },
                    { key: 'pending_logs', label: 'Pending' },
                    { key: 'sent_logs', label: 'Sent' },
                    { key: 'failed_logs', label: 'Failed' },
                    { key: 'unread_in_app', label: 'Unread in-app' },
                ]" :key="metric.key" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">{{ t(metric.label) }}</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-950">{{ dashboard.metrics[metric.key] }}</p>
                </article>
            </div>

            <section class="mt-4 grid gap-4 xl:grid-cols-2">
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">{{ t('Channel status monitoring') }}</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="channel in dashboard.channel_statuses" :key="channel.channel" class="rounded-2xl bg-slate-50 px-4 py-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-950">{{ pretty(channel.channel) }}</p>
                                    <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ channel.provider }}</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="channel.configured ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                                    {{ channel.configured ? t('Configured') : t('Fallback') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">{{ t('Failed deliveries') }}</h2>
                    <div v-if="dashboard.failed_logs.length === 0" class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-600">
                        {{ t('No failed notification deliveries were recorded.') }}
                    </div>
                    <div v-else class="mt-4 space-y-3">
                        <div v-for="log in dashboard.failed_logs" :key="log.id" class="rounded-2xl border border-slate-200 px-4 py-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-950">{{ log.template_code }} · {{ pretty(log.channel) }}</p>
                                    <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ log.user.name || log.user.email || t('Unknown user') }}</p>
                                </div>
                                <button type="button" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800" @click="retryLog(log.id)">
                                    {{ t('Retry') }}
                                </button>
                            </div>
                            <p class="mt-3 text-sm text-slate-600">{{ log.response_payload.error || t('Unknown failure') }}</p>
                        </div>
                    </div>
                </article>
            </section>

            <section class="mt-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">{{ t('Notification logs') }}</h2>
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
                                <td class="px-4 py-3">{{ log.template_code }}</td>
                                <td class="px-4 py-3">{{ pretty(log.channel) }}</td>
                                <td class="px-4 py-3">{{ pretty(log.status) }}</td>
                                <td class="px-4 py-3">{{ log.retry_count }}</td>
                                <td class="px-4 py-3">{{ log.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">{{ t('Template manager') }}</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ t('Edit notification titles, bodies, and channels used by the mobile app and email.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-800 transition hover:bg-slate-50"
                        @click="syncTemplates"
                    >
                        {{ t('Sync templates') }}
                    </button>
                </div>

                <div v-if="(dashboard.templates ?? []).length === 0" class="mt-4 rounded-2xl bg-slate-50 px-4 py-6 text-sm text-slate-600">
                    {{ t('No templates yet. Click Sync templates to load the engine catalog.') }}
                </div>

                <div class="mt-4 space-y-4">
                    <form
                        v-for="template in dashboard.templates"
                        :key="template.id"
                        class="rounded-2xl border border-slate-200 px-4 py-4"
                        @submit.prevent="updateTemplate(template)"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-mono text-sm font-semibold text-slate-950">{{ template.code }}</p>
                                <p class="mt-1 text-xs text-slate-500">v{{ template.version }}</p>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="drafts[template.id].is_active" type="checkbox" class="rounded border-slate-300">
                                {{ t('Active') }}
                            </label>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Name') }}</span>
                                <input
                                    v-model="drafts[template.id].name"
                                    type="text"
                                    class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                    required
                                >
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Subject') }}</span>
                                <input
                                    v-model="drafts[template.id].subject"
                                    type="text"
                                    class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                >
                            </label>
                        </div>

                        <label class="mt-3 block">
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Body') }}</span>
                            <textarea
                                v-model="drafts[template.id].body"
                                rows="4"
                                class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm leading-6"
                                required
                            />
                        </label>

                        <div class="mt-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Channels') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="channel in availableChannels"
                                    :key="channel"
                                    type="button"
                                    class="rounded-full px-3 py-1.5 text-xs font-medium"
                                    :class="drafts[template.id].channels.includes(channel)
                                        ? 'bg-slate-950 text-white'
                                        : 'bg-slate-100 text-slate-600'"
                                    @click="toggleChannel(template.id, channel)"
                                >
                                    {{ pretty(channel) }}
                                </button>
                            </div>
                        </div>

                        <p v-if="(template.variables ?? []).length" class="mt-3 text-xs text-slate-500">
                            {{ t('Variables') }}:
                            <span class="font-mono">{{ formatVariables(template.variables) }}</span>
                        </p>

                        <div class="mt-4 flex justify-end">
                            <button
                                type="submit"
                                class="inline-flex rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
                                :disabled="drafts[template.id]?.saving || (drafts[template.id]?.channels?.length ?? 0) === 0"
                            >
                                {{ drafts[template.id]?.saving ? t('Saving...') : t('Save changes') }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </AdminModulePage>
    </AdminLayout>
</template>
