<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminBadge from '../../components/AdminBadge.vue';
import AdminButton from '../../components/AdminButton.vue';
import TemplateManager from '../components/TemplateManager.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { useAdminConfirm } from '../../composables/useAdminConfirm';

const props = defineProps({
    dashboard: { type: Object, required: true },
});

const { t, isArabic } = useAdminLocale();
const { confirm } = useAdminConfirm();
const page = usePage();
const firebaseInput = ref(null);
const showFirebaseSetup = ref(false);
const previewMode = ref('phone');
const previewLocale = ref('ar');

const tabAliases = {
    overview: 'status',
    channels: 'status',
    logs: 'status',
    templates: 'messages',
    tools: 'send',
    status: 'status',
    messages: 'messages',
    send: 'send',
};

const requestedParams = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);
const activeTab = ref(tabAliases[requestedParams.get('tab')] ?? 'send');
showFirebaseSetup.value = requestedParams.get('setup') === 'firebase';

const flashSuccess = computed(() => page.props.flash?.success ?? '');
const flashError = computed(() => page.props.flash?.error ?? '');
const permissions = computed(() => page.props.auth?.user?.permissions ?? []);
const canManageFirebase = computed(() => permissions.value.includes('notifications.manage-templates'));

const failedLogs = computed(() => props.dashboard.failed_logs ?? []);
const recentLogs = computed(() => (props.dashboard.logs ?? []).slice(0, 20));
const failedCount = computed(() => props.dashboard.metrics?.failed_logs ?? failedLogs.value.length);
const sentCount = computed(() => props.dashboard.metrics?.sent_logs ?? 0);
const firebase = computed(() => props.dashboard.firebase ?? {
    configured: false,
    project_id: null,
    client_email: null,
    path: '',
    has_backup: false,
});

const tabs = computed(() => [
    { id: 'send', label: t('Compose') },
    { id: 'status', label: t('Activity') },
    { id: 'messages', label: t('Templates') },
]);

const pushTargets = computed(() => props.dashboard.push_targets ?? []);
const pushAudienceCount = computed(() => props.dashboard.push_audience_count ?? pushTargets.value.length);
const templates = computed(() => props.dashboard.templates ?? []);
const templateCategories = computed(() => props.dashboard.template_categories ?? []);
const availableChannels = computed(() => props.dashboard.available_channels ?? ['in_app', 'email', 'push', 'sms', 'whatsapp']);

const pushForm = useForm({
    user_id: 'all',
    title: '',
    body: '',
});

const selectedTemplateId = ref('');

const activeTemplates = computed(() =>
    (templates.value ?? []).filter((item) => item.is_active !== false),
);

const templateLabel = (template) => {
    if (!template) return '';
    return isArabic.value
        ? (template.label_ar || template.name || template.code)
        : (template.label || template.name || template.code);
};

const isPreviewArabic = computed(() => previewLocale.value === 'ar');

const contentFromTemplate = (template, locale = previewLocale.value) => {
    if (!template) {
        return { title: '', body: '' };
    }

    if (locale === 'ar') {
        return {
            title: template.translations?.ar?.subject || template.subject || template.label_ar || template.name || '',
            body: template.translations?.ar?.body || template.body || '',
        };
    }

    return {
        title: template.subject || template.label || template.name || '',
        body: template.body || '',
    };
};

const applyTemplate = (templateId, { switchChannel = true } = {}) => {
    selectedTemplateId.value = templateId === null || templateId === undefined ? '' : String(templateId);

    if (!selectedTemplateId.value) {
        return;
    }

    const template = activeTemplates.value.find((item) => String(item.id) === selectedTemplateId.value);
    if (!template) return;

    const content = contentFromTemplate(template, previewLocale.value);
    pushForm.title = content.title;
    pushForm.body = content.body;

    if (!switchChannel) {
        return;
    }

    const channels = template.channels ?? [];
    if (channels.includes('email') && !channels.includes('push')) {
        previewMode.value = 'email';
    } else if (channels.includes('push')) {
        previewMode.value = 'phone';
    }
};

const setPreviewLocale = (locale) => {
    previewLocale.value = locale;
    if (selectedTemplateId.value) {
        applyTemplate(selectedTemplateId.value, { switchChannel: false });
    }
};

const syncTemplates = () => {
    router.post(route('admin.notifications.templates.sync'), {}, { preserveScroll: true });
};

const hasPreviewContent = computed(() => Boolean(pushForm.title.trim() || pushForm.body.trim()));
const previewTitle = computed(() => pushForm.title.trim() || t('Notification title'));
const previewBody = computed(() => pushForm.body.trim() || t('Your message will appear here'));
const titleCount = computed(() => pushForm.title?.length ?? 0);
const bodyCount = computed(() => pushForm.body?.length ?? 0);
const canSend = computed(() => firebase.value.configured && pushAudienceCount.value > 0);
const previewDir = computed(() => (isPreviewArabic.value ? 'rtl' : 'ltr'));

const selectedTarget = computed(() => {
    if (String(pushForm.user_id) === 'all') return null;
    return pushTargets.value.find((item) => String(item.id) === String(pushForm.user_id)) ?? null;
});

const recipientLabel = computed(() => {
    if (String(pushForm.user_id) === 'all') {
        return `${t('Everyone')} · ${pushAudienceCount}`;
    }
    return selectedTarget.value?.name || selectedTarget.value?.email || t('Customer');
});

const emailToLabel = computed(() => {
    if (String(pushForm.user_id) === 'all') {
        return t('Everyone');
    }
    return selectedTarget.value?.email || selectedTarget.value?.name || t('Customer');
});

const firebaseForm = useForm({ credentials: null });
const firebaseTestForm = useForm({});
const firebaseDisconnectForm = useForm({});
const firebaseRestoreForm = useForm({});

watch(activeTab, (tabId) => {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url);
});

watch(showFirebaseSetup, (open) => {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    if (open) url.searchParams.set('setup', 'firebase');
    else url.searchParams.delete('setup');
    window.history.replaceState({}, '', url);
});

const sendTestPush = async () => {
    if (String(pushForm.user_id) === 'all') {
        if (!await confirm({
            title: t('Confirm action'),
            message: t('Send this message to everyone with the app?'),
            confirmLabel: t('Send'),
        })) {
            return;
        }
    }
    pushForm.post(route('admin.notifications.push-test'), { preserveScroll: true });
};

const onFirebaseSelected = (event) => {
    firebaseForm.credentials = event.target.files?.[0] ?? null;
};

const uploadFirebase = () => {
    if (!firebaseForm.credentials) return;
    firebaseForm.post(route('admin.notifications.firebase-credentials'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            firebaseForm.reset('credentials');
            if (firebaseInput.value) firebaseInput.value.value = '';
        },
    });
};

const testFirebase = () => {
    firebaseTestForm.post(route('admin.notifications.firebase-test'), { preserveScroll: true });
};

const disconnectFirebase = async () => {
    if (!await confirm({
        title: t('Confirm action'),
        message: t('Hide the current Firebase file temporarily? You can upload again or restore later.'),
        confirmLabel: t('Disconnect'),
    })) {
        return;
    }
    firebaseDisconnectForm.post(route('admin.notifications.firebase-disconnect'), { preserveScroll: true });
};

const restoreFirebase = () => {
    firebaseRestoreForm.post(route('admin.notifications.firebase-restore'), { preserveScroll: true });
};

const retryLog = (id) => {
    useForm({}).post(route('admin.notifications.retry', id), { preserveScroll: true });
};

const channelName = (channel) => {
    const map = {
        in_app: t('App'),
        email: t('Email'),
        push: t('Phone'),
        sms: t('SMS'),
        whatsapp: t('WhatsApp'),
    };
    return map[channel] ?? channel;
};

const statusLabel = (status) => {
    const map = {
        sent: t('Sent'),
        delivered: t('Delivered'),
        pending: t('Sending…'),
        queued: t('Sending…'),
        failed: t('Failed'),
    };
    return map[String(status || '').toLowerCase()] ?? status;
};

const statusVariant = (status) => {
    const map = {
        sent: 'success',
        delivered: 'success',
        pending: 'warning',
        queued: 'warning',
        failed: 'error',
    };
    return map[String(status || '').toLowerCase()] ?? 'neutral';
};

const logLabel = (log) => {
    if (!log) return t('Unknown');
    return isArabic.value
        ? (log.template_label_ar || log.template_code)
        : (log.template_label || log.template_code);
};
</script>

<template>
    <Head :title="t('Notifications')" />

    <AdminLayout title="Notifications" description="Write once. See it on the phone before you send.">
        <section class="relative isolate space-y-5">
            <div
                class="pointer-events-none absolute inset-x-0 -top-4 -z-10 h-72 overflow-hidden rounded-[1.75rem]"
                aria-hidden="true"
            >
                <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_12%_0%,rgba(47,109,184,0.12),transparent_50%),radial-gradient(ellipse_at_88%_8%,rgba(240,180,41,0.10),transparent_42%)]" />
            </div>

            <div class="flex flex-col gap-3 rounded-[1.5rem] border border-slate-200/80 bg-white/90 px-3 py-3 shadow-[0_8px_30px_rgba(15,23,42,0.04)] backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-4">
                <nav class="inline-flex w-fit items-center gap-1 rounded-full bg-slate-100/90 p-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-semibold transition"
                        :class="activeTab === tab.id
                            ? 'bg-[#2f6db8] text-white shadow-sm'
                            : 'text-slate-500 hover:text-slate-900'"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.label }}
                    </button>
                </nav>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-1 text-sm text-slate-500">
                    <span>
                        <span class="font-semibold text-slate-800 tabular-nums">{{ sentCount }}</span>
                        {{ t('Sent') }}
                    </span>
                    <span class="hidden h-3 w-px bg-slate-200 sm:inline-block" />
                    <span>
                        <span class="font-semibold tabular-nums" :class="failedCount > 0 ? 'text-rose-600' : 'text-slate-800'">{{ failedCount }}</span>
                        {{ t('Failed') }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="firebase.configured
                            ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/80'
                            : 'bg-amber-50 text-amber-800 ring-1 ring-amber-200/80'"
                        @click="showFirebaseSetup = !showFirebaseSetup"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="firebase.configured ? 'bg-emerald-500' : 'bg-amber-500'"
                        />
                        Firebase · {{ firebase.configured ? t('Ready') : t('Setup') }}
                    </button>
                </div>
            </div>

            <p v-if="flashSuccess" class="rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-800">
                {{ flashSuccess }}
            </p>
            <p v-if="flashError" class="rounded-2xl border border-rose-200 bg-rose-50/90 px-4 py-3 text-sm text-rose-800">
                {{ flashError }}
            </p>

            <div
                v-if="showFirebaseSetup"
                class="overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-white p-5 shadow-[0_10px_30px_rgba(15,23,42,0.04)] sm:p-6"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">Firebase</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ firebase.project_id || t('No project connected') }}</p>
                    </div>
                    <AdminBadge
                        :variant="firebase.configured ? 'success' : 'warning'"
                        :label="firebase.configured ? t('Ready') : t('Setup')"
                    />
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <AdminButton variant="secondary" size="sm" :processing="firebaseTestForm.processing" :disabled="!firebase.configured" @click="testFirebase">
                        {{ t('Test') }}
                    </AdminButton>
                    <AdminButton
                        v-if="canManageFirebase && firebase.configured"
                        variant="ghost"
                        size="sm"
                        :processing="firebaseDisconnectForm.processing"
                        @click="disconnectFirebase"
                    >
                        {{ t('Disconnect') }}
                    </AdminButton>
                    <AdminButton
                        v-if="canManageFirebase && !firebase.configured && firebase.has_backup"
                        variant="secondary"
                        size="sm"
                        :processing="firebaseRestoreForm.processing"
                        @click="restoreFirebase"
                    >
                        {{ t('Restore') }}
                    </AdminButton>
                    <AdminButton variant="secondary" size="sm" @click="showFirebaseSetup = false">
                        {{ t('Close') }}
                    </AdminButton>
                </div>

                <form
                    v-if="canManageFirebase"
                    class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-end"
                    @submit.prevent="uploadFirebase"
                >
                    <label class="block min-w-0 flex-1">
                        <span class="text-sm font-medium text-slate-700">{{ t('JSON file') }}</span>
                        <input
                            ref="firebaseInput"
                            type="file"
                            accept=".json,application/json"
                            class="mt-1.5 block w-full text-sm text-slate-600 file:me-3 file:rounded-xl file:border-0 file:bg-[#2f6db8]/10 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[#255991]"
                            @change="onFirebaseSelected"
                        >
                    </label>
                    <AdminButton type="submit" :processing="firebaseForm.processing" :disabled="!firebaseForm.credentials">
                        {{ t('Upload') }}
                    </AdminButton>
                </form>
            </div>

            <!-- Compose -->
            <div v-show="activeTab === 'send'" class="grid items-stretch gap-5 xl:grid-cols-[1.15fr_0.85fr]">
                <form
                    class="relative flex flex-col overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-white shadow-[0_16px_50px_rgba(15,23,42,0.06)]"
                    @submit.prevent="sendTestPush"
                >
                    <div class="border-b border-slate-100 px-6 py-5 sm:px-7">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-[#2f6db8]">{{ t('Compose') }}</p>
                                <h3 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">
                                    {{ t('Notifications') }}
                                </h3>
                            </div>
                            <div class="rounded-full bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200">
                                {{ recipientLabel }}
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 space-y-5 px-6 py-6 sm:px-7">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-medium text-slate-700">{{ t('Templates') }}</span>
                                <div class="flex items-center gap-3">
                                    <div class="inline-flex rounded-full bg-white p-0.5 ring-1 ring-slate-200">
                                        <button
                                            type="button"
                                            class="rounded-full px-2.5 py-1 text-[0.7rem] font-semibold transition"
                                            :class="previewLocale === 'ar' ? 'bg-[#2f6db8] text-white' : 'text-slate-500'"
                                            @click="setPreviewLocale('ar')"
                                        >
                                            العربية
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-full px-2.5 py-1 text-[0.7rem] font-semibold transition"
                                            :class="previewLocale === 'en' ? 'bg-[#2f6db8] text-white' : 'text-slate-500'"
                                            @click="setPreviewLocale('en')"
                                        >
                                            English
                                        </button>
                                    </div>
                                    <button
                                        v-if="activeTemplates.length === 0"
                                        type="button"
                                        class="text-xs font-semibold text-[#2f6db8] hover:underline"
                                        @click="syncTemplates"
                                    >
                                        {{ t('Sync') }}
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="text-xs font-semibold text-[#2f6db8] hover:underline"
                                        @click="activeTab = 'messages'"
                                    >
                                        {{ t('Manage') }}
                                    </button>
                                </div>
                            </div>

                            <p v-if="activeTemplates.length === 0" class="mt-2 text-sm text-slate-500">
                                {{ t('No templates yet. Press Sync.') }}
                            </p>

                            <select
                                v-else
                                :value="selectedTemplateId"
                                class="mt-2.5 w-full appearance-none rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 outline-none transition focus:border-[#2f6db8] focus:ring-4 focus:ring-[#2f6db8]/10"
                                @change="applyTemplate($event.target.value)"
                            >
                                <option value="">{{ t('Custom message') }}</option>
                                <option
                                    v-for="template in activeTemplates"
                                    :key="template.id"
                                    :value="template.id"
                                >
                                    {{ templateLabel(template) }}
                                </option>
                            </select>

                            <p class="mt-2 text-xs text-slate-500">
                                {{ t('Customer emails use Arabic by default when the account language is Arabic.') }}
                            </p>
                        </div>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-slate-700">{{ t('To') }}</span>
                            <select
                                v-model="pushForm.user_id"
                                class="w-full appearance-none rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm font-medium text-slate-900 outline-none transition focus:border-[#2f6db8] focus:bg-white focus:ring-4 focus:ring-[#2f6db8]/10"
                                required
                            >
                                <option value="all">{{ t('Everyone') }} ({{ pushAudienceCount }})</option>
                                <option v-for="target in pushTargets" :key="target.id" :value="target.id">
                                    {{ target.name || target.email }}
                                </option>
                            </select>
                        </label>

                        <label class="block">
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-slate-700">{{ t('Title') }}</span>
                                <span class="text-xs tabular-nums text-slate-400">{{ titleCount }}/120</span>
                            </div>
                            <input
                                v-model="pushForm.title"
                                type="text"
                                maxlength="120"
                                :dir="previewDir"
                                :placeholder="t('Notification title')"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-lg font-semibold tracking-tight text-slate-950 outline-none transition placeholder:font-normal placeholder:text-slate-300 focus:border-[#2f6db8] focus:ring-4 focus:ring-[#2f6db8]/10"
                                required
                            >
                        </label>

                        <label class="block">
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-slate-700">{{ t('Message') }}</span>
                                <span class="text-xs tabular-nums text-slate-400">{{ bodyCount }}/500</span>
                            </div>
                            <textarea
                                v-model="pushForm.body"
                                rows="6"
                                maxlength="500"
                                :dir="previewDir"
                                :placeholder="t('Your message will appear here')"
                                class="w-full resize-none rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3.5 text-[0.95rem] leading-7 text-slate-800 outline-none transition placeholder:text-slate-300 focus:border-[#2f6db8] focus:bg-white focus:ring-4 focus:ring-[#2f6db8]/10"
                                required
                            />
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-6 py-4 sm:px-7">
                        <p class="text-sm text-slate-500">
                            <span v-if="!firebase.configured" class="text-amber-700">{{ t('Setup') }} Firebase</span>
                            <span v-else-if="pushAudienceCount === 0" class="text-amber-700">{{ t('No phones registered yet.') }}</span>
                            <span v-else>{{ t('Phone') }} · {{ pushAudienceCount }} {{ t('devices') }}</span>
                        </p>
                        <AdminButton
                            type="submit"
                            :processing="pushForm.processing"
                            :disabled="!canSend"
                        >
                            {{ String(pushForm.user_id) === 'all' ? t('Send to everyone') : t('Send') }}
                        </AdminButton>
                    </div>
                </form>

                <aside
                    class="relative flex min-h-[34rem] flex-col overflow-hidden rounded-[1.5rem] shadow-[0_24px_60px_rgba(11,21,36,0.28)]"
                    :class="previewMode === 'phone' ? 'bg-[#0b1524]' : 'border border-slate-200/80 bg-[#f4f6f9]'"
                >
                    <div
                        v-if="previewMode === 'phone'"
                        class="pointer-events-none absolute inset-0"
                        aria-hidden="true"
                    >
                        <div class="absolute -start-20 top-0 h-52 w-52 rounded-full bg-[#2f6db8]/25 blur-3xl" />
                        <div class="absolute -end-16 bottom-0 h-56 w-56 rounded-full bg-[#f0b429]/18 blur-3xl" />
                    </div>

                    <div
                        class="relative z-10 flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4"
                        :class="previewMode === 'phone' ? 'border-white/10' : 'border-slate-200 bg-white'"
                    >
                        <div>
                            <p class="text-sm font-semibold" :class="previewMode === 'phone' ? 'text-white' : 'text-slate-950'">
                                {{ t('Live preview') }}
                            </p>
                            <p class="mt-0.5 text-xs" :class="previewMode === 'phone' ? 'text-white/45' : 'text-slate-500'">
                                {{ t('What the customer will see') }}
                            </p>
                        </div>

                        <div
                            class="inline-flex rounded-full p-1"
                            :class="previewMode === 'phone' ? 'bg-white/10' : 'bg-slate-100'"
                        >
                            <button
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                                :class="previewMode === 'phone'
                                    ? 'bg-[#2f6db8] text-white shadow-sm'
                                    : (previewMode === 'email' ? 'text-slate-500 hover:text-slate-800' : 'text-white/55 hover:text-white')"
                                @click="previewMode = 'phone'"
                            >
                                {{ t('Phone') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                                :class="previewMode === 'email'
                                    ? 'bg-white text-slate-950 shadow-sm ring-1 ring-slate-200'
                                    : 'text-white/55 hover:text-white'"
                                @click="previewMode = 'email'"
                            >
                                {{ t('Email') }}
                            </button>
                        </div>
                    </div>

                    <!-- Phone preview -->
                    <div v-show="previewMode === 'phone'" class="relative z-10 flex flex-1 items-center justify-center px-6 py-8">
                        <div class="w-full max-w-[280px]">
                            <div class="rounded-[2.4rem] bg-gradient-to-b from-[#3a4456] to-[#1a212c] p-[9px] shadow-[0_30px_60px_rgba(0,0,0,0.4)] ring-1 ring-white/10">
                                <div class="relative overflow-hidden rounded-[1.95rem] bg-[#101826]">
                                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_35%_15%,rgba(47,109,184,0.45),transparent_45%),linear-gradient(165deg,#1c2a40_0%,#0d1420_58%,#152033_100%)]" />

                                    <div class="relative px-4 pb-9 pt-3">
                                        <div class="mx-auto mb-4 h-[1.45rem] w-[6.5rem] rounded-full bg-black/85" />

                                        <div class="flex items-center justify-between px-0.5 text-[0.65rem] font-medium text-white/65">
                                            <span>9:41</span>
                                            <span class="h-2 w-3 rounded-[1px] border border-white/45">
                                                <span class="m-[1px] block h-full w-2/3 rounded-[1px] bg-white/75" />
                                            </span>
                                        </div>

                                        <div class="mt-9 text-center">
                                            <p class="text-[3.1rem] font-thin leading-none tracking-tight text-white">9:41</p>
                                            <p class="mt-1.5 text-xs font-medium text-white/50">{{ t('Today') }}</p>
                                        </div>

                                        <div
                                            class="mt-8 origin-top transition-all duration-400 ease-out"
                                            :class="hasPreviewContent
                                                ? 'translate-y-0 opacity-100'
                                                : 'translate-y-1 opacity-65'"
                                        >
                                            <div class="rounded-[1.25rem] border border-white/12 bg-white/15 p-3 shadow-lg backdrop-blur-xl" :dir="previewDir">
                                                <div class="flex items-start gap-2.5">
                                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-[0.75rem] bg-white">
                                                        <img
                                                            src="/images/app_logo.png"
                                                            alt="Booke"
                                                            class="h-full w-full object-cover"
                                                        >
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <p class="truncate text-[0.65rem] font-semibold tracking-[0.08em] text-white/65 uppercase">
                                                                Booke
                                                            </p>
                                                            <p class="text-[0.62rem] text-white/35">now</p>
                                                        </div>
                                                        <p class="mt-1 truncate text-sm font-semibold text-white">
                                                            {{ previewTitle }}
                                                        </p>
                                                        <p class="mt-0.5 line-clamp-3 text-[0.78rem] leading-5 text-white/70">
                                                            {{ previewBody }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email preview -->
                    <div v-show="previewMode === 'email'" class="relative z-10 flex flex-1 items-stretch p-5">
                        <div class="flex w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_12px_40px_rgba(15,23,42,0.08)]">
                            <div class="flex items-center gap-1.5 border-b border-slate-100 bg-slate-50 px-4 py-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-[#ff5f57]" />
                                <span class="h-2.5 w-2.5 rounded-full bg-[#febc2e]" />
                                <span class="h-2.5 w-2.5 rounded-full bg-[#28c840]" />
                                <span class="ms-3 truncate text-xs font-medium text-slate-400">inbox · Booke</span>
                            </div>

                            <div class="border-b border-slate-100 px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#2f6db8]/10 ring-1 ring-[#2f6db8]/15">
                                        <img src="/images/app_logo.png" alt="Booke" class="h-7 w-7 object-cover">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="truncate text-sm font-semibold text-slate-950">Booke</p>
                                            <p class="shrink-0 text-[0.7rem] text-slate-400">{{ t('Today') }}</p>
                                        </div>
                                        <p class="mt-0.5 truncate text-xs text-slate-500">
                                            {{ t('To') }}: {{ emailToLabel }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex-1 px-5 py-5" :dir="previewDir">
                                <p
                                    class="text-lg font-semibold tracking-tight text-slate-950 transition"
                                    :class="hasPreviewContent ? 'opacity-100' : 'opacity-50'"
                                >
                                    {{ previewTitle }}
                                </p>
                                <div class="mt-4 whitespace-pre-wrap text-sm leading-7 text-slate-600 transition" :class="hasPreviewContent ? 'opacity-100' : 'opacity-50'">
                                    {{ previewBody }}
                                </div>
                            </div>

                            <div class="border-t border-slate-100 bg-slate-50 px-5 py-3 text-center text-[0.7rem] text-slate-400">
                                Booke · {{ t('Email') }}
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <!-- Activity -->
            <div v-show="activeTab === 'status'" class="space-y-4">
                <div v-if="failedLogs.length" class="overflow-hidden rounded-[1.5rem] border border-rose-200/80 bg-white shadow-[0_10px_30px_rgba(225,29,72,0.05)]">
                    <div class="border-b border-rose-100 bg-rose-50/80 px-5 py-3.5">
                        <h3 class="text-sm font-semibold text-rose-900">{{ t('Failed') }}</h3>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div
                            v-for="log in failedLogs"
                            :key="`fail-${log.id}`"
                            class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-950">{{ logLabel(log) }}</p>
                                <p class="mt-1 truncate text-sm text-slate-500">
                                    {{ log.user.name || log.user.email }} · {{ channelName(log.channel) }}
                                </p>
                            </div>
                            <AdminButton size="sm" @click="retryLog(log.id)">{{ t('Retry') }}</AdminButton>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-white shadow-[0_10px_30px_rgba(15,23,42,0.04)]">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <h3 class="text-sm font-semibold text-slate-950">{{ t('Activity') }}</h3>
                    </div>

                    <p v-if="!recentLogs.length" class="px-5 py-14 text-center text-sm text-slate-500">
                        {{ t('Nothing sent yet.') }}
                    </p>

                    <div v-else class="divide-y divide-slate-100">
                        <div
                            v-for="log in recentLogs"
                            :key="log.id"
                            class="flex items-start justify-between gap-3 px-5 py-4 transition hover:bg-slate-50/70"
                        >
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#2f6db8]/10 text-[#2f6db8]">
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                        <path d="M10 2a4 4 0 00-4 4v2.26A6 6 0 004 14h12a6 6 0 00-2-5.74V6a4 4 0 00-4-4zm0 16a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0010 18z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-950">{{ logLabel(log) }}</p>
                                    <p class="mt-1 truncate text-sm text-slate-500">
                                        {{ log.user.name || log.user.email }} · {{ channelName(log.channel) }} · {{ log.created_at }}
                                    </p>
                                </div>
                            </div>
                            <AdminBadge :variant="statusVariant(log.status)" :label="statusLabel(log.status)" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Templates -->
            <div v-show="activeTab === 'messages'">
                <TemplateManager
                    :templates="templates"
                    :template-categories="templateCategories"
                    :available-channels="availableChannels"
                />
            </div>
        </section>
    </AdminLayout>
</template>
