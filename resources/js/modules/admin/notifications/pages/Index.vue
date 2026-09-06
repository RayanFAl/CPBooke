<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminBadge from '../../components/AdminBadge.vue';
import AdminButton from '../../components/AdminButton.vue';
import TemplateManager from '../components/TemplateManager.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { useAdminConfirm } from '../../composables/useAdminConfirm';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
});

const { t, isArabic } = useAdminLocale();
const { confirm } = useAdminConfirm();
const page = usePage();
const firebaseInput = ref(null);
const moreMenuOpen = ref(false);
const showFirebaseSetup = ref(false);

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
const requestedTab = requestedParams.get('tab');
const activeTab = ref(tabAliases[requestedTab] ?? 'send');
showFirebaseSetup.value = requestedParams.get('setup') === 'firebase';

const flashSuccess = computed(() => page.props.flash?.success ?? '');
const flashError = computed(() => page.props.flash?.error ?? '');
const permissions = computed(() => page.props.auth?.user?.permissions ?? []);
const canManageFirebase = computed(() => permissions.value.includes('notifications.manage-templates'));

const failedLogs = computed(() => props.dashboard.failed_logs ?? []);
const recentLogs = computed(() => (props.dashboard.logs ?? []).slice(0, 12));
const failedCount = computed(() => props.dashboard.metrics?.failed_logs ?? failedLogs.value.length);
const sentCount = computed(() => props.dashboard.metrics?.sent_logs ?? 0);
const firebase = computed(() => props.dashboard.firebase ?? {
    configured: false,
    project_id: null,
    client_email: null,
    path: '',
    has_backup: false,
});

const tabs = [
    { id: 'send', label: 'Send' },
    { id: 'status', label: 'Status' },
    { id: 'messages', label: 'Texts' },
];

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

const firebaseForm = useForm({
    credentials: null,
});

const firebaseTestForm = useForm({});
const firebaseDisconnectForm = useForm({});
const firebaseRestoreForm = useForm({});

const setTab = (tabId) => {
    activeTab.value = tabId;
};

const openFirebaseSetup = () => {
    moreMenuOpen.value = false;
    showFirebaseSetup.value = true;
};

const closeFirebaseSetup = () => {
    showFirebaseSetup.value = false;
};

watch(activeTab, (tabId) => {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url);
});

watch(showFirebaseSetup, (open) => {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    if (open) {
        url.searchParams.set('setup', 'firebase');
    } else {
        url.searchParams.delete('setup');
    }
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

    pushForm.post(route('admin.notifications.push-test'), {
        preserveScroll: true,
    });
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
            if (firebaseInput.value) {
                firebaseInput.value.value = '';
            }
        },
    });
};

const testFirebase = () => {
    firebaseTestForm.post(route('admin.notifications.firebase-test'), {
        preserveScroll: true,
    });
};

const disconnectFirebase = async () => {
    if (!await confirm({
        title: t('Confirm action'),
        message: t('Hide the current Firebase file temporarily? You can upload again or restore later.'),
        confirmLabel: t('Disconnect'),
    })) {
        return;
    }

    firebaseDisconnectForm.post(route('admin.notifications.firebase-disconnect'), {
        preserveScroll: true,
    });
};

const restoreFirebase = () => {
    firebaseRestoreForm.post(route('admin.notifications.firebase-restore'), {
        preserveScroll: true,
    });
};

const retryLog = (id) => {
    useForm({}).post(route('admin.notifications.retry', id), {
        preserveScroll: true,
    });
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
        sent: 'OK',
        delivered: 'OK',
        pending: 'Sending…',
        queued: 'Sending…',
        failed: 'Failed',
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

const inputClass = 'mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-slate-400';
</script>

<template>
    <Head :title="t('Notifications')" />

    <AdminLayout title="Notifications" description="Send messages, check status, edit texts.">
        <section class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-950">{{ t('Notifications') }}</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ t('Send · check · edit texts') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="rounded-lg bg-emerald-50 px-3 py-1.5 font-medium text-emerald-800">
                            {{ sentCount }} {{ t('OK') }}
                        </span>
                        <span
                            class="rounded-lg px-3 py-1.5 font-medium"
                            :class="failedCount > 0 ? 'bg-rose-50 text-rose-800' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ failedCount }} {{ t('Failed') }}
                        </span>

                        <div class="relative">
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                                :aria-expanded="moreMenuOpen"
                                :aria-label="t('More')"
                                @click="moreMenuOpen = !moreMenuOpen"
                            >
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                                    <path d="M3 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM8.5 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM14 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z" />
                                </svg>
                            </button>
                            <div
                                v-if="moreMenuOpen"
                                class="absolute end-0 z-50 mt-1 min-w-[12rem] overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                            >
                                <button
                                    type="button"
                                    class="block w-full px-4 py-2 text-start text-sm text-slate-700 transition hover:bg-slate-50"
                                    @click="openFirebaseSetup"
                                >
                                    {{ t('Firebase setup') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="flashSuccess" class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ flashSuccess }}</p>
                <p v-if="flashError" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ flashError }}</p>

                <div class="mt-4 flex gap-1 rounded-lg bg-slate-100 p-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition"
                        :class="activeTab === tab.id ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="setTab(tab.id)"
                    >
                        {{ t(tab.label) }}
                    </button>
                </div>
            </div>

            <!-- Hidden Firebase setup (⋯ menu) -->
            <div v-if="showFirebaseSetup" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">{{ t('Firebase setup') }}</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ t('Upload the service account JSON so phone notifications work.') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <AdminBadge
                            :variant="firebase.configured ? 'success' : 'warning'"
                            :label="firebase.configured ? 'Ready' : 'Missing file'"
                        />
                        <button
                            type="button"
                            class="rounded-lg px-2 py-1 text-sm text-slate-500 hover:bg-white hover:text-slate-800"
                            @click="closeFirebaseSetup"
                        >
                            {{ t('Close') }}
                        </button>
                    </div>
                </div>

                <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                    <div class="rounded-lg bg-white px-3 py-2">
                        <dt class="text-xs text-slate-500">{{ t('Project') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ firebase.project_id || '—' }}</dd>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2">
                        <dt class="text-xs text-slate-500">{{ t('Account') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ firebase.client_email || '—' }}</dd>
                    </div>
                </dl>

                <p class="mt-2 text-xs text-slate-400">{{ firebase.path }}</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <AdminButton
                        variant="secondary"
                        :processing="firebaseTestForm.processing"
                        :disabled="!firebase.configured"
                        @click="testFirebase"
                    >
                        {{ t('Test connection') }}
                    </AdminButton>

                    <AdminButton
                        v-if="canManageFirebase && firebase.configured"
                        variant="ghost"
                        :processing="firebaseDisconnectForm.processing"
                        @click="disconnectFirebase"
                    >
                        {{ t('Disconnect') }}
                    </AdminButton>

                    <AdminButton
                        v-if="canManageFirebase && !firebase.configured && firebase.has_backup"
                        variant="secondary"
                        :processing="firebaseRestoreForm.processing"
                        @click="restoreFirebase"
                    >
                        {{ t('Restore previous') }}
                    </AdminButton>
                </div>

                <p class="mt-2 text-xs text-slate-500">
                    {{ firebase.configured
                        ? t('Checks the current file with Google. Does not send any message.')
                        : t('Disconnected. Upload a JSON file to reconnect, or restore the previous one.') }}
                </p>

                <form
                    v-if="canManageFirebase"
                    class="mt-4 flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-end"
                    @submit.prevent="uploadFirebase"
                >
                    <label class="block min-w-0 flex-1">
                        <span class="text-sm text-slate-600">{{ t('JSON file') }}</span>
                        <input
                            ref="firebaseInput"
                            type="file"
                            accept=".json,application/json"
                            class="mt-1.5 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-800 hover:file:bg-slate-100"
                            @change="onFirebaseSelected"
                        >
                        <p v-if="firebaseForm.errors.credentials" class="mt-1 text-xs text-rose-600">
                            {{ firebaseForm.errors.credentials }}
                        </p>
                    </label>
                    <AdminButton
                        type="submit"
                        :processing="firebaseForm.processing"
                        :disabled="!firebaseForm.credentials"
                    >
                        {{ firebase.configured ? t('Replace file') : t('Reconnect') }}
                    </AdminButton>
                </form>

                <p v-else class="mt-3 text-sm text-slate-500">
                    {{ t('Ask an admin with template access to upload the Firebase file.') }}
                </p>
            </div>

            <!-- Send -->
            <div v-show="activeTab === 'send'" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <h3 class="text-base font-semibold text-slate-950">{{ t('New message') }}</h3>

                <form class="mt-4 space-y-3" @submit.prevent="sendTestPush">
                    <label class="block">
                        <span class="text-sm text-slate-600">{{ t('To') }}</span>
                        <select v-model="pushForm.user_id" :class="inputClass" required>
                            <option value="all">{{ t('Everyone') }} ({{ pushAudienceCount }})</option>
                            <option v-for="target in pushTargets" :key="target.id" :value="target.id">
                                {{ target.name || target.email }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm text-slate-600">{{ t('Title') }}</span>
                        <input v-model="pushForm.title" type="text" maxlength="120" :class="inputClass" required>
                    </label>

                    <label class="block">
                        <span class="text-sm text-slate-600">{{ t('Message') }}</span>
                        <textarea v-model="pushForm.body" rows="3" maxlength="500" :class="inputClass" required />
                    </label>

                    <AdminButton type="submit" :processing="pushForm.processing" :disabled="pushAudienceCount === 0">
                        {{ String(pushForm.user_id) === 'all' ? t('Send to everyone') : t('Send') }}
                    </AdminButton>

                    <p v-if="pushAudienceCount === 0" class="text-sm text-amber-700">
                        {{ t('No phones registered yet.') }}
                    </p>
                </form>
            </div>

            <!-- Status -->
            <div v-show="activeTab === 'status'" class="space-y-4">
                <div v-if="failedLogs.length > 0" class="rounded-xl border border-rose-200 bg-white p-4 shadow-sm sm:p-5">
                    <h3 class="text-base font-semibold text-slate-950">{{ t('Failed') }}</h3>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="log in failedLogs"
                            :key="log.id"
                            class="flex flex-col gap-2 rounded-lg bg-rose-50 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900">{{ logLabel(log) }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ log.user.name || log.user.email }} · {{ channelName(log.channel) }}
                                </p>
                            </div>
                            <AdminButton size="sm" @click="retryLog(log.id)">{{ t('Retry') }}</AdminButton>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h3 class="text-base font-semibold text-slate-950">{{ t('Recent') }}</h3>

                    <p v-if="recentLogs.length === 0" class="mt-3 text-sm text-slate-500">
                        {{ t('Nothing sent yet.') }}
                    </p>

                    <div v-else class="mt-3 divide-y divide-slate-100">
                        <div
                            v-for="log in recentLogs"
                            :key="log.id"
                            class="flex items-start justify-between gap-3 py-3"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900">{{ logLabel(log) }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ log.user.name || log.user.email }} · {{ channelName(log.channel) }} · {{ log.created_at }}
                                </p>
                            </div>
                            <AdminBadge :variant="statusVariant(log.status)" :label="statusLabel(log.status)" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Texts -->
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
