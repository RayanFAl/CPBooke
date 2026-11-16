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
    test_mail_url: { type: String, required: true },
    notifications_url: { type: String, required: true },
});

const { t } = useAdminLocale();
const page = usePage();
const activeTab = ref('company');

const tabs = [
    { id: 'company', label: 'Company' },
    { id: 'contact', label: 'Contact & Emails' },
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
    noreply_email: props.settings.noreply_email ?? '',
    info_email: props.settings.info_email ?? '',
    feedback_email: props.settings.feedback_email ?? '',
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
    mail_mailer: props.settings.mail_mailer ?? '',
    mail_host: props.settings.mail_host ?? '',
    mail_port: props.settings.mail_port ?? '',
    mail_encryption: props.settings.mail_encryption ?? '',
    mail_username: props.settings.mail_username ?? '',
    mail_password: '',
    clear_mail_password: false,
    sms_sender_name: props.settings.sms_sender_name ?? '',
    sms_endpoint: props.settings.sms_endpoint ?? '',
    sms_token: '',
    clear_sms_token: false,
    whatsapp_sender_name: props.settings.whatsapp_sender_name ?? '',
    whatsapp_endpoint: props.settings.whatsapp_endpoint ?? '',
    whatsapp_token: '',
    clear_whatsapp_token: false,
    feature_maintenance_mode: Boolean(props.settings.feature_maintenance_mode),
    feature_chat_enabled: Boolean(props.settings.feature_chat_enabled),
    feature_legacy_order_create: Boolean(props.settings.feature_legacy_order_create),
    section: 'company',
});

const smsTokenSet = computed(() => Boolean(props.settings.sms_token_set));
const whatsappTokenSet = computed(() => Boolean(props.settings.whatsapp_token_set));
const mailPasswordSet = computed(() => Boolean(props.settings.mail_password_set));

const flashSuccess = computed(() => page.props.flash?.success ?? '');
const flashError = computed(() => page.props.flash?.error ?? '');

const testMailForm = useForm({
    recipient: '',
});

const submit = () => {
    form.section = activeTab.value;
    form.transform((data) => ({
        ...data,
        default_commission_percent:
            data.default_commission_percent === '' || data.default_commission_percent === null
                ? null
                : Number(data.default_commission_percent),
        logo_path: data.logo_path || null,
        support_email: data.support_email || null,
        support_phone: data.support_phone || null,
        noreply_email: data.noreply_email || null,
        info_email: data.info_email || null,
        feedback_email: data.feedback_email || null,
        sms_endpoint: data.sms_endpoint || null,
        whatsapp_endpoint: data.whatsapp_endpoint || null,
        sms_token: data.clear_sms_token ? null : (data.sms_token || null),
        whatsapp_token: data.clear_whatsapp_token ? null : (data.whatsapp_token || null),
        mail_mailer: data.mail_mailer || null,
        mail_host: data.mail_host || null,
        mail_port:
            data.mail_port === '' || data.mail_port === null ? null : Number(data.mail_port),
        mail_encryption: data.mail_encryption || null,
        mail_username: data.mail_username || null,
        mail_password: data.clear_mail_password ? null : (data.mail_password || null),
    })).put(props.update_url, {
        preserveScroll: true,
    });
};

const sendTestMail = () => {
    testMailForm
        .transform((data) => ({
            recipient: typeof data.recipient === 'string' ? data.recipient.trim() || null : null,
        }))
        .post(props.test_mail_url, {
            preserveScroll: true,
            onFinish: () => {
                testMailForm.recipient = testMailForm.recipient ?? '';
            },
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
        description="Platform configuration for company profile, contact emails, currency, margins, channels, and feature flags."
    >
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#2f6db8]">{{ t('Configuration') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Settings') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {{ t('Operational settings live here: company profile, mailboxes, SMTP, SMS/WhatsApp gateways, and feature flags.') }}
                </p>
                <p v-if="flashSuccess" class="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ flashSuccess }}
                </p>
                <p v-if="flashError" class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ flashError }}
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
                    <label class="block text-sm md:col-span-2">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Logo path (optional)') }}</span>
                        <input v-model="form.logo_path" type="text" class="w-full rounded-2xl border-slate-200" placeholder="storage path or URL" />
                    </label>
                </div>

                <div v-show="activeTab === 'contact'" class="space-y-6">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">{{ t('Customer contact') }}</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ t('Shown in the app, email footers, and Reply-To for notifications.') }}
                        </p>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('Support email') }}</span>
                                <input v-model="form.support_email" type="email" class="w-full rounded-2xl border-slate-200" placeholder="support@booke.ly" />
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('Support phone') }}</span>
                                <input v-model="form.support_phone" type="text" class="w-full rounded-2xl border-slate-200" placeholder="+218…" />
                            </label>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-6">
                        <h3 class="text-sm font-semibold text-slate-900">{{ t('Notification & mailbox emails') }}</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ t('Mailbox roles and display names used as From/Reply-To across the app.') }}
                        </p>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('No-reply / From email') }}</span>
                                <input v-model="form.noreply_email" type="email" class="w-full rounded-2xl border-slate-200" placeholder="no-reply@booke.ly" />
                                <span class="mt-1 block text-xs text-slate-500">{{ t('Used as From for OTPs, bookings, and system notifications.') }}</span>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('Email from name') }}</span>
                                <input v-model="form.email_from_name" type="text" class="w-full rounded-2xl border-slate-200" />
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('Info email') }}</span>
                                <input v-model="form.info_email" type="email" class="w-full rounded-2xl border-slate-200" placeholder="info@booke.ly" />
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('Feedback email') }}</span>
                                <input v-model="form.feedback_email" type="email" class="w-full rounded-2xl border-slate-200" placeholder="feedback@booke.ly" />
                            </label>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-6">
                        <h3 class="text-sm font-semibold text-slate-900">{{ t('SMTP delivery') }}</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ t('Configure the outbound mail server here. Leave mailer empty to keep using .env defaults until you save SMTP values.') }}
                        </p>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('Mailer') }}</span>
                                <select v-model="form.mail_mailer" class="w-full rounded-2xl border-slate-200">
                                    <option value="">{{ t('Use .env default') }}</option>
                                    <option value="smtp">SMTP</option>
                                    <option value="log">{{ t('Log only (dev)') }}</option>
                                </select>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('SMTP host') }}</span>
                                <input v-model="form.mail_host" type="text" class="w-full rounded-2xl border-slate-200" placeholder="smtp.example.com" />
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('SMTP port') }}</span>
                                <input v-model="form.mail_port" type="number" min="1" max="65535" class="w-full rounded-2xl border-slate-200" placeholder="587" />
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('Encryption') }}</span>
                                <select v-model="form.mail_encryption" class="w-full rounded-2xl border-slate-200">
                                    <option value="">{{ t('Auto / none') }}</option>
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="none">{{ t('None') }}</option>
                                </select>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('SMTP username') }}</span>
                                <input v-model="form.mail_username" type="text" class="w-full rounded-2xl border-slate-200" autocomplete="off" />
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('SMTP password') }}</span>
                                <input
                                    v-model="form.mail_password"
                                    type="password"
                                    autocomplete="new-password"
                                    class="w-full rounded-2xl border-slate-200"
                                    :placeholder="mailPasswordSet ? '•••••••• (saved — enter new to replace)' : t('Paste password')"
                                />
                                <span v-if="mailPasswordSet" class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span>{{ t('Password is saved.') }}</span>
                                    <label class="inline-flex items-center gap-1.5 text-rose-700">
                                        <input v-model="form.clear_mail_password" type="checkbox" class="rounded border-slate-300" />
                                        {{ t('Clear password') }}
                                    </label>
                                </span>
                            </label>
                        </div>
                        <div class="mt-4 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <label class="block flex-1 min-w-[200px] text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('Test recipient (optional)') }}</span>
                                <input
                                    v-model="testMailForm.recipient"
                                    type="email"
                                    name="test_mail_recipient"
                                    autocomplete="off"
                                    class="w-full rounded-2xl border-slate-200 bg-white"
                                    :placeholder="t('e.g. you@example.com')"
                                />
                            </label>
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-800 disabled:opacity-60"
                                :disabled="testMailForm.processing"
                                @click="sendTestMail"
                            >
                                {{ testMailForm.processing ? t('Sending…') : t('Send test email') }}
                            </button>
                        </div>
                        <p v-if="testMailForm.errors.recipient" class="mt-2 text-sm text-rose-700">
                            {{ testMailForm.errors.recipient }}
                        </p>
                    </div>
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

                <div v-show="activeTab === 'channels'" class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex items-center gap-3 text-sm text-slate-800">
                            <input v-model="form.channel_email_enabled" type="checkbox" class="rounded border-slate-300" />
                            {{ t('Email enabled') }}
                        </label>
                        <p class="text-sm text-slate-500">
                            {{ t('From name and mailbox addresses are under Contact & Emails.') }}
                        </p>
                        <label class="flex items-center gap-3 text-sm text-slate-800 md:col-span-2">
                            <input v-model="form.channel_push_enabled" type="checkbox" class="rounded border-slate-300" />
                            {{ t('Push enabled') }}
                        </label>
                        <p class="md:col-span-2 text-xs text-slate-500">
                            {{ t('Upload Firebase service account JSON under Admin → Notifications.') }}
                            <a :href="notifications_url" class="font-medium text-[#2f6db8] underline">{{ t('Open notifications setup') }}</a>
                        </p>
                    </div>

                    <div class="border-t border-slate-100 pt-6">
                        <h3 class="text-sm font-semibold text-slate-900">{{ t('SMS gateway') }}</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ t('Needs an API endpoint and a bearer token. Leave the token blank to keep the current one.') }}
                        </p>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="flex items-center gap-3 text-sm text-slate-800 md:col-span-2">
                                <input v-model="form.channel_sms_enabled" type="checkbox" class="rounded border-slate-300" />
                                {{ t('SMS enabled') }}
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('SMS sender name') }}</span>
                                <input v-model="form.sms_sender_name" type="text" class="w-full rounded-2xl border-slate-200" />
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('SMS endpoint') }}</span>
                                <input v-model="form.sms_endpoint" type="url" class="w-full rounded-2xl border-slate-200" placeholder="https://…" />
                            </label>
                            <label class="block text-sm md:col-span-2">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('SMS token') }}</span>
                                <input
                                    v-model="form.sms_token"
                                    type="password"
                                    autocomplete="new-password"
                                    class="w-full rounded-2xl border-slate-200"
                                    :placeholder="smsTokenSet ? '•••••••• (saved — enter new to replace)' : t('Paste token')"
                                />
                                <span v-if="smsTokenSet" class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span>{{ t('Token is saved.') }}</span>
                                    <label class="inline-flex items-center gap-1.5 text-rose-700">
                                        <input v-model="form.clear_sms_token" type="checkbox" class="rounded border-slate-300" />
                                        {{ t('Clear token') }}
                                    </label>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-6">
                        <h3 class="text-sm font-semibold text-slate-900">{{ t('WhatsApp gateway') }}</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ t('Needs an API endpoint and a bearer token. Leave the token blank to keep the current one.') }}
                        </p>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="flex items-center gap-3 text-sm text-slate-800 md:col-span-2">
                                <input v-model="form.channel_whatsapp_enabled" type="checkbox" class="rounded border-slate-300" />
                                {{ t('WhatsApp enabled') }}
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('WhatsApp sender name') }}</span>
                                <input v-model="form.whatsapp_sender_name" type="text" class="w-full rounded-2xl border-slate-200" />
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('WhatsApp endpoint') }}</span>
                                <input v-model="form.whatsapp_endpoint" type="url" class="w-full rounded-2xl border-slate-200" placeholder="https://…" />
                            </label>
                            <label class="block text-sm md:col-span-2">
                                <span class="mb-1 block font-medium text-slate-800">{{ t('WhatsApp token') }}</span>
                                <input
                                    v-model="form.whatsapp_token"
                                    type="password"
                                    autocomplete="new-password"
                                    class="w-full rounded-2xl border-slate-200"
                                    :placeholder="whatsappTokenSet ? '•••••••• (saved — enter new to replace)' : t('Paste token')"
                                />
                                <span v-if="whatsappTokenSet" class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span>{{ t('Token is saved.') }}</span>
                                    <label class="inline-flex items-center gap-1.5 text-rose-700">
                                        <input v-model="form.clear_whatsapp_token" type="checkbox" class="rounded border-slate-300" />
                                        {{ t('Clear token') }}
                                    </label>
                                </span>
                            </label>
                        </div>
                    </div>
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
