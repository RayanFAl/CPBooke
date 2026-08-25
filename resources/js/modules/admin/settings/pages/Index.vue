<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    channel_statuses: { type: Array, default: () => [] },
    can_manage_sensitive_flags: { type: Boolean, default: false },
    update_url: { type: String, required: true },
});

const { t } = useAdminLocale();
const page = usePage();
const activeTab = ref('company');

const tabs = [
    { id: 'company', label: 'Company' },
    { id: 'localization', label: 'Currency' },
    { id: 'margins', label: 'Margins' },
    { id: 'channels', label: 'Channels' },
    { id: 'features', label: 'Feature Flags' },
    { id: 'integrations', label: 'Integrations' },
];

const form = useForm({
    company_name: props.settings.company_name ?? '',
    company_address: props.settings.company_address ?? '',
    support_email: props.settings.support_email ?? '',
    support_phone: props.settings.support_phone ?? '',
    tax_id: props.settings.tax_id ?? '',
    logo_path: props.settings.logo_path ?? '',
    default_currency: props.settings.default_currency ?? 'LYD',
    timezone: props.settings.timezone ?? 'UTC',
    locale: props.settings.locale ?? 'en',
    default_commission_percent: props.settings.default_commission_percent ?? '',
    channel_email_enabled: Boolean(props.settings.channel_email_enabled),
    channel_sms_enabled: Boolean(props.settings.channel_sms_enabled),
    channel_whatsapp_enabled: Boolean(props.settings.channel_whatsapp_enabled),
    channel_push_enabled: Boolean(props.settings.channel_push_enabled),
    email_from_name: props.settings.email_from_name ?? '',
    sms_sender_name: props.settings.sms_sender_name ?? '',
    whatsapp_sender_name: props.settings.whatsapp_sender_name ?? '',
    feature_maintenance_mode: Boolean(props.settings.feature_maintenance_mode),
    feature_chat_enabled: Boolean(props.settings.feature_chat_enabled),
    feature_legacy_order_create: Boolean(props.settings.feature_legacy_order_create),
    section: 'company',
});

const flashSuccess = computed(() => page.props.flash?.success ?? '');

const submit = () => {
    form.section = activeTab.value;
    form.transform((data) => ({
        ...data,
        default_commission_percent:
            data.default_commission_percent === '' || data.default_commission_percent === null
                ? null
                : Number(data.default_commission_percent),
        logo_path: data.logo_path || null,
    })).put(props.update_url, {
        preserveScroll: true,
    });
};

const modeBadgeClass = (mode) => {
    if (mode === 'configured') {
        return 'bg-emerald-100 text-emerald-800';
    }

    if (mode === 'simulated') {
        return 'bg-amber-100 text-amber-800';
    }

    return 'bg-rose-100 text-rose-800';
};
</script>

<template>
    <AdminLayout
        title="Settings"
        description="Platform configuration for company profile, currency, margins, channels, and feature flags."
    >
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Configuration') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Settings') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {{ t('Editable operational settings. Secrets and API tokens remain in environment variables.') }}
                </p>
                <p v-if="flashSuccess" class="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ flashSuccess }}
                </p>
                <p class="mt-3 text-xs text-slate-500">
                    {{ t('Version') }}: {{ settings.settings_version }}
                    <span v-if="settings.updated_at"> · {{ settings.updated_at }}</span>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="rounded-2xl px-4 py-2 text-sm font-medium transition"
                    :class="activeTab === tab.id ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    @click="activeTab = tab.id"
                >
                    {{ t(tab.label) }}
                </button>
            </div>

            <form class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div v-show="activeTab === 'company'" class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Company name') }}</span>
                        <input v-model="form.company_name" type="text" class="w-full rounded-2xl border-slate-200" />
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Tax ID') }}</span>
                        <input v-model="form.tax_id" type="text" class="w-full rounded-2xl border-slate-200" />
                    </label>
                    <label class="block text-sm md:col-span-2">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Address') }}</span>
                        <textarea v-model="form.company_address" rows="2" class="w-full rounded-2xl border-slate-200" />
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Support email') }}</span>
                        <input v-model="form.support_email" type="email" class="w-full rounded-2xl border-slate-200" />
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Support phone') }}</span>
                        <input v-model="form.support_phone" type="text" class="w-full rounded-2xl border-slate-200" />
                    </label>
                    <label class="block text-sm md:col-span-2">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Logo path (optional)') }}</span>
                        <input v-model="form.logo_path" type="text" class="w-full rounded-2xl border-slate-200" placeholder="storage path or URL" />
                    </label>
                </div>

                <div v-show="activeTab === 'localization'" class="grid gap-4 md:grid-cols-3">
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Default currency') }}</span>
                        <input v-model="form.default_currency" type="text" maxlength="3" class="w-full rounded-2xl border-slate-200 uppercase" />
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Timezone') }}</span>
                        <input v-model="form.timezone" type="text" class="w-full rounded-2xl border-slate-200" />
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Locale') }}</span>
                        <input v-model="form.locale" type="text" class="w-full rounded-2xl border-slate-200" />
                    </label>
                </div>

                <div v-show="activeTab === 'margins'" class="space-y-3">
                    <p class="text-sm text-slate-600">
                        {{ t('Resolution order: payload hints → provider commission → platform default → zero margin. Historical orders are never rewritten.') }}
                    </p>
                    <label class="block max-w-sm text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Default commission %') }}</span>
                        <input v-model="form.default_commission_percent" type="number" min="0" max="100" step="0.01" class="w-full rounded-2xl border-slate-200" />
                    </label>
                </div>

                <div v-show="activeTab === 'channels'" class="grid gap-4 md:grid-cols-2">
                    <label class="flex items-center gap-3 text-sm text-slate-800">
                        <input v-model="form.channel_email_enabled" type="checkbox" class="rounded border-slate-300" />
                        {{ t('Email enabled') }}
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Email from name') }}</span>
                        <input v-model="form.email_from_name" type="text" class="w-full rounded-2xl border-slate-200" />
                    </label>
                    <label class="flex items-center gap-3 text-sm text-slate-800">
                        <input v-model="form.channel_sms_enabled" type="checkbox" class="rounded border-slate-300" />
                        {{ t('SMS enabled') }}
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('SMS sender name') }}</span>
                        <input v-model="form.sms_sender_name" type="text" class="w-full rounded-2xl border-slate-200" />
                    </label>
                    <label class="flex items-center gap-3 text-sm text-slate-800">
                        <input v-model="form.channel_whatsapp_enabled" type="checkbox" class="rounded border-slate-300" />
                        {{ t('WhatsApp enabled') }}
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('WhatsApp sender name') }}</span>
                        <input v-model="form.whatsapp_sender_name" type="text" class="w-full rounded-2xl border-slate-200" />
                    </label>
                    <label class="flex items-center gap-3 text-sm text-slate-800 md:col-span-2">
                        <input v-model="form.channel_push_enabled" type="checkbox" class="rounded border-slate-300" />
                        {{ t('Push enabled') }}
                    </label>
                    <p class="md:col-span-2 text-xs text-slate-500">
                        {{ t('Gateway tokens stay in .env (SMS_*, WHATSAPP_*, FIREBASE_CREDENTIALS).') }}
                    </p>
                </div>

                <div v-show="activeTab === 'features'" class="space-y-4">
                    <label class="flex items-center gap-3 text-sm text-slate-800">
                        <input
                            v-model="form.feature_maintenance_mode"
                            type="checkbox"
                            class="rounded border-slate-300"
                            :disabled="!can_manage_sensitive_flags"
                        />
                        {{ t('Maintenance mode') }}
                        <span v-if="!can_manage_sensitive_flags" class="text-xs text-slate-500">({{ t('super admin only') }})</span>
                    </label>
                    <label class="flex items-center gap-3 text-sm text-slate-800">
                        <input v-model="form.feature_chat_enabled" type="checkbox" class="rounded border-slate-300" />
                        {{ t('Support chat enabled') }}
                    </label>
                    <label class="flex items-center gap-3 text-sm text-slate-800">
                        <input
                            v-model="form.feature_legacy_order_create"
                            type="checkbox"
                            class="rounded border-slate-300"
                            :disabled="!can_manage_sensitive_flags"
                        />
                        {{ t('Legacy order create') }}
                        <span v-if="!can_manage_sensitive_flags" class="text-xs text-slate-500">({{ t('super admin only') }})</span>
                    </label>
                </div>

                <div v-show="activeTab === 'integrations'" class="space-y-3">
                    <div
                        v-for="status in channel_statuses"
                        :key="status.channel"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3"
                    >
                        <div>
                            <p class="text-sm font-medium capitalize text-slate-900">{{ status.channel }}</p>
                            <p class="text-xs text-slate-500">{{ status.provider }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="status.enabled ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-600'"
                            >
                                {{ status.enabled ? t('Enabled') : t('Disabled') }}
                            </span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="modeBadgeClass(status.mode)">
                                {{ status.mode }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab !== 'integrations'" class="mt-6 flex justify-end">
                    <button
                        type="submit"
                        class="rounded-2xl bg-slate-950 px-5 py-2.5 text-sm font-medium text-white disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? t('Saving…') : t('Save settings') }}
                    </button>
                </div>

                <div v-if="Object.keys(form.errors).length" class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
