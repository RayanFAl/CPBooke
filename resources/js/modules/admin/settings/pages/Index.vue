<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
    channelStatus: {
        type: Object,
        required: true,
    },
    currencyOptions: {
        type: Array,
        required: true,
    },
});

const { t } = useAdminLocale();
const page = usePage();
const activeTab = ref('company');

const tabs = computed(() => [
    { id: 'company', label: t('Company') },
    { id: 'currency', label: t('Currency & locale') },
    { id: 'channels', label: t('Channels') },
    { id: 'flags', label: t('Feature flags') },
]);

const form = useForm({
    company_legal_name: props.settings.company_legal_name ?? '',
    company_display_name: props.settings.company_display_name ?? '',
    support_email: props.settings.support_email ?? '',
    support_phone: props.settings.support_phone ?? '',
    website_url: props.settings.website_url ?? '',
    tax_id: props.settings.tax_id ?? '',
    company_address: props.settings.company_address ?? '',
    default_currency: props.settings.default_currency ?? 'LYD',
    timezone: props.settings.timezone ?? 'Africa/Tripoli',
    default_locale: props.settings.default_locale ?? 'en',
    default_margin_percent: props.settings.default_margin_percent ?? '',
    email_enabled: Boolean(props.settings.email_enabled),
    sms_enabled: Boolean(props.settings.sms_enabled),
    whatsapp_enabled: Boolean(props.settings.whatsapp_enabled),
    push_enabled: Boolean(props.settings.push_enabled),
    mail_from_name: props.settings.mail_from_name ?? '',
    sms_sender_id: props.settings.sms_sender_id ?? '',
    maintenance_mode: Boolean(props.settings.maintenance_mode),
    support_chat_enabled: Boolean(props.settings.support_chat_enabled),
    orders_legacy_create_enabled: Boolean(props.settings.orders_legacy_create_enabled),
    home_offers_enabled: Boolean(props.settings.home_offers_enabled),
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);

const channelLabel = (configured) => (configured ? t('Credentials configured') : t('Missing credentials in .env'));

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            default_margin_percent: data.default_margin_percent === '' ? null : data.default_margin_percent,
            website_url: data.website_url === '' ? null : data.website_url,
            support_email: data.support_email === '' ? null : data.support_email,
            support_phone: data.support_phone === '' ? null : data.support_phone,
            tax_id: data.tax_id === '' ? null : data.tax_id,
            company_address: data.company_address === '' ? null : data.company_address,
            mail_from_name: data.mail_from_name === '' ? null : data.mail_from_name,
            sms_sender_id: data.sms_sender_id === '' ? null : data.sms_sender_id,
        }))
        .put(route('admin.settings.update'), {
            preserveScroll: true,
        });
};

const tabClass = (id) => (activeTab.value === id
    ? 'bg-slate-950 text-white'
    : 'text-slate-600 hover:bg-slate-100');
</script>

<template>
    <AdminLayout
        title="Settings"
        description="Platform configuration for company identity, currency, channels, and feature flags."
    >
        <Head title="Settings" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                            {{ t('Configuration') }}
                        </p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('System settings') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ t('Secrets stay in environment variables. This screen stores editable business settings only.') }}
                        </p>
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ t('Version') }} {{ settings.settings_version }}
                    </p>
                </div>

                <p v-if="flashSuccess" class="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ flashSuccess }}
                </p>

                <div class="mt-6 flex flex-wrap gap-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-medium transition"
                        :class="tabClass(tab.id)"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div v-show="activeTab === 'company'" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Company') }}</h3>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-700">{{ t('Legal name') }}</span>
                            <input v-model="form.company_legal_name" type="text" class="w-full rounded-xl border-slate-300" />
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-700">{{ t('Display name') }}</span>
                            <input v-model="form.company_display_name" type="text" class="w-full rounded-xl border-slate-300" />
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-700">{{ t('Support email') }}</span>
                            <input v-model="form.support_email" type="email" class="w-full rounded-xl border-slate-300" />
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-700">{{ t('Support phone') }}</span>
                            <input v-model="form.support_phone" type="text" class="w-full rounded-xl border-slate-300" />
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-700">{{ t('Website') }}</span>
                            <input v-model="form.website_url" type="url" class="w-full rounded-xl border-slate-300" />
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-700">{{ t('Tax / registration ID') }}</span>
                            <input v-model="form.tax_id" type="text" class="w-full rounded-xl border-slate-300" />
                        </label>
                        <label class="block text-sm md:col-span-2">
                            <span class="mb-1 block text-slate-700">{{ t('Address') }}</span>
                            <textarea v-model="form.company_address" rows="3" class="w-full rounded-xl border-slate-300" />
                        </label>
                    </div>
                </div>

                <div v-show="activeTab === 'currency'" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Currency & locale') }}</h3>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-700">{{ t('Default currency') }}</span>
                            <select v-model="form.default_currency" class="w-full rounded-xl border-slate-300">
                                <option v-for="currency in currencyOptions" :key="currency" :value="currency">
                                    {{ currency }}
                                </option>
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-700">{{ t('Timezone') }}</span>
                            <input v-model="form.timezone" type="text" class="w-full rounded-xl border-slate-300" />
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-700">{{ t('Default locale') }}</span>
                            <input v-model="form.default_locale" type="text" class="w-full rounded-xl border-slate-300" />
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-700">{{ t('Default margin %') }}</span>
                            <input v-model="form.default_margin_percent" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border-slate-300" />
                        </label>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">
                        {{ t('Historical orders keep their original currency. Margin applies only when no provider/hint rate exists.') }}
                    </p>
                </div>

                <div v-show="activeTab === 'channels'" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Channels') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ t('Toggle delivery channels. API tokens remain in .env and are never shown here.') }}
                    </p>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                            <span>
                                <span class="block font-medium text-slate-900">{{ t('Email') }}</span>
                                <span class="text-xs text-slate-500">{{ channelLabel(channelStatus.email) }}</span>
                            </span>
                            <input v-model="form.email_enabled" type="checkbox" class="rounded border-slate-300" />
                        </label>
                        <label class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                            <span>
                                <span class="block font-medium text-slate-900">{{ t('SMS') }}</span>
                                <span class="text-xs text-slate-500">{{ channelLabel(channelStatus.sms) }}</span>
                            </span>
                            <input v-model="form.sms_enabled" type="checkbox" class="rounded border-slate-300" />
                        </label>
                        <label class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                            <span>
                                <span class="block font-medium text-slate-900">{{ t('WhatsApp') }}</span>
                                <span class="text-xs text-slate-500">{{ channelLabel(channelStatus.whatsapp) }}</span>
                            </span>
                            <input v-model="form.whatsapp_enabled" type="checkbox" class="rounded border-slate-300" />
                        </label>
                        <label class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                            <span>
                                <span class="block font-medium text-slate-900">{{ t('Push') }}</span>
                                <span class="text-xs text-slate-500">{{ channelLabel(channelStatus.push) }}</span>
                            </span>
                            <input v-model="form.push_enabled" type="checkbox" class="rounded border-slate-300" />
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-700">{{ t('Mail from name') }}</span>
                            <input v-model="form.mail_from_name" type="text" class="w-full rounded-xl border-slate-300" />
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-700">{{ t('SMS sender ID') }}</span>
                            <input v-model="form.sms_sender_id" type="text" class="w-full rounded-xl border-slate-300" />
                        </label>
                    </div>
                </div>

                <div v-show="activeTab === 'flags'" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Feature flags') }}</h3>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                            <span class="font-medium text-slate-900">{{ t('Maintenance mode') }}</span>
                            <input v-model="form.maintenance_mode" type="checkbox" class="rounded border-slate-300" />
                        </label>
                        <label class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                            <span class="font-medium text-slate-900">{{ t('Support chat enabled') }}</span>
                            <input v-model="form.support_chat_enabled" type="checkbox" class="rounded border-slate-300" />
                        </label>
                        <label class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                            <span class="font-medium text-slate-900">{{ t('Legacy order create') }}</span>
                            <input v-model="form.orders_legacy_create_enabled" type="checkbox" class="rounded border-slate-300" />
                        </label>
                        <label class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                            <span class="font-medium text-slate-900">{{ t('Home offers enabled') }}</span>
                            <input v-model="form.home_offers_enabled" type="checkbox" class="rounded border-slate-300" />
                        </label>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="rounded-full bg-slate-950 px-5 py-2.5 text-sm font-medium text-white disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? t('Saving…') : t('Save settings') }}
                    </button>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
