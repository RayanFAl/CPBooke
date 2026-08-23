<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { useAdminConfirm } from '../../composables/useAdminConfirm';

const props = defineProps({
    supplier: { type: Object, required: true },
    api_configs: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    api_monitoring: { type: Array, default: () => [] },
    api_logs: { type: Array, default: () => [] },
    api_log_filters: { type: Object, default: () => ({}) },
    api_options: { type: Object, required: true },
    can_manage: { type: Boolean, default: false },
    can_manage_api_config: { type: Boolean, default: false },
    can_view_credentials: { type: Boolean, default: false },
    can_view_wallets: { type: Boolean, default: false },
});

const { locale, t } = useAdminLocale();
const { confirm } = useAdminConfirm();
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const flashInfo = computed(() => page.props.flash?.info ?? null);

const maskedSecret = '••••••••••••';
const activeEnvironment = ref('sandbox');

const configByEnvironment = computed(() => {
    const map = {};

    props.api_configs.forEach((config) => {
        map[config.environment] = config;
    });

    return map;
});

const activeConfig = computed(() => configByEnvironment.value[activeEnvironment.value] ?? null);

const configForm = useForm({
    environment: 'sandbox',
    base_url: '',
    auth_type: 'api_key',
    api_key: '',
    api_secret: '',
    access_token: '',
    refresh_token: '',
    webhook_url: '',
    timeout: 30,
    custom_headers: {},
    status: 'active',
    confirm_production: false,
});

const servicesForm = useForm({
    services: props.services.map((service) => ({
        service: service.service,
        enabled: service.enabled,
        configuration: service.configuration ?? {},
    })),
});

const logFilterForm = reactive({
    service: props.api_log_filters.service ?? '',
    endpoint: props.api_log_filters.endpoint ?? '',
    success: props.api_log_filters.success ?? '',
    status_code: props.api_log_filters.status_code ?? '',
    date_from: props.api_log_filters.date_from ?? '',
    date_to: props.api_log_filters.date_to ?? '',
});

const formatMoney = (amount, currency) => {
    if (amount === null || amount === undefined || amount === '') {
        return '—';
    }

    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: currency || props.supplier.default_currency || 'LYD',
    }).format(Number(amount));
};

const environmentLabel = (environment) => {
    if (environment === 'production') {
        return t('Production');
    }

    return t('Sandbox');
};

const environmentBadgeClass = (environment) => {
    if (environment === 'production') {
        return 'bg-emerald-100 text-emerald-800';
    }

    return 'bg-amber-100 text-amber-800';
};

const authTypeLabel = (authType) => {
    const labels = {
        bearer_token: t('Bearer Token'),
        api_key: t('API Key'),
        api_key_secret: t('API Key + Secret'),
        oauth2: t('OAuth2'),
        custom: t('Custom'),
    };

    return labels[authType] ?? authType;
};

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const applyLogFilters = () => {
    router.get(route('admin.suppliers.show', props.supplier.id), {
        ...logFilterForm,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetLogFilters = () => {
    logFilterForm.service = '';
    logFilterForm.endpoint = '';
    logFilterForm.success = '';
    logFilterForm.status_code = '';
    logFilterForm.date_from = '';
    logFilterForm.date_to = '';
    applyLogFilters();
};

const loadConfigForm = (environment) => {
    activeEnvironment.value = environment;
    const config = configByEnvironment.value[environment];

    configForm.environment = environment;
    configForm.base_url = config?.base_url ?? '';
    configForm.auth_type = config?.auth_type ?? 'api_key';
    configForm.api_key = '';
    configForm.api_secret = '';
    configForm.access_token = '';
    configForm.refresh_token = '';
    configForm.webhook_url = config?.webhook_url ?? '';
    configForm.timeout = config?.timeout ?? 30;
    configForm.custom_headers = config?.custom_headers ?? {};
    configForm.status = config?.status ?? 'active';
    configForm.confirm_production = false;
};

const submitConfig = () => {
    configForm.environment = activeEnvironment.value;

    configForm.post(route('admin.suppliers.api-config.upsert', props.supplier.id), {
        preserveScroll: true,
    });
};

const submitServices = () => {
    servicesForm.post(route('admin.suppliers.services.sync', props.supplier.id), {
        preserveScroll: true,
    });
};

const testConnection = (environment) => {
    useForm({}).post(route('admin.suppliers.api-config.test', [props.supplier.id, environment]), {
        preserveScroll: true,
    });
};

const disableConfig = async (environment) => {
    if (!await confirm({
        title: 'Confirm action',
        message: t('Disable this API configuration?'),
        confirmLabel: 'Disable',
        variant: 'danger',
    })) {
        return;
    }

    useForm({}).post(route('admin.suppliers.api-config.disable', [props.supplier.id, environment]), {
        preserveScroll: true,
    });
};

const auditCredentialAccess = (environment) => {
    useForm({}).post(route('admin.suppliers.api-config.audit-credentials', [props.supplier.id, environment]), {
        preserveScroll: true,
    });
};

loadConfigForm('sandbox');
</script>

<template>
    <AdminLayout>
        <Head :title="supplier.name" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <Link :href="route('admin.suppliers.index')" class="text-sm font-medium text-cyan-700">← {{ t('Back to providers') }}</Link>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ supplier.name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ supplier.key }} · {{ supplier.status }} · {{ supplier.integration_status }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Link
                            v-if="can_manage"
                            :href="route('admin.suppliers.edit', supplier.id)"
                            class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                        >
                            {{ t('Edit profile') }}
                        </Link>
                        <Link
                            v-if="can_view_wallets"
                            :href="route('admin.provider-wallets.create', { provider_id: supplier.id })"
                            class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700"
                        >
                            {{ t('Create wallet') }}
                        </Link>
                    </div>
                </div>
                <p v-if="flashSuccess" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ flashSuccess }}</p>
                <p v-if="flashError" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ flashError }}</p>
                <p v-if="flashInfo" class="mt-4 rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-800">{{ flashInfo }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Commission') }}</p>
                    <p class="mt-2 text-xl font-semibold">{{ supplier.commission_rate != null ? `${supplier.commission_rate}%` : '—' }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Settlement cycle') }}</p>
                    <p class="mt-2 text-xl font-semibold">{{ supplier.settlement_cycle }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Credit limit') }}</p>
                    <p class="mt-2 text-xl font-semibold">{{ formatMoney(supplier.credit_limit, supplier.default_currency) }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Currency') }}</p>
                    <p class="mt-2 text-xl font-semibold">{{ supplier.default_currency }}</p>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">{{ t('API Configuration') }}</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ t('Connection credentials are stored encrypted and never sent directly from the browser to the provider.') }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            v-for="environment in api_options.environments"
                            :key="environment"
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm font-medium"
                            :class="activeEnvironment === environment ? 'bg-slate-950 text-white' : 'border border-slate-300 text-slate-700'"
                            @click="loadConfigForm(environment)"
                        >
                            {{ environmentLabel(environment) }}
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide" :class="environmentBadgeClass(activeEnvironment)">
                        {{ activeEnvironment === 'production' ? '🟢 PRODUCTION' : '🟡 SANDBOX' }}
                    </span>
                    <span v-if="activeConfig?.status === 'disabled'" class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800">
                        {{ t('Disabled') }}
                    </span>
                </div>

                <div v-if="activeConfig" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    <p>{{ t('Last test') }}: {{ activeConfig.last_test_status || t('Never') }}</p>
                    <p v-if="activeConfig.last_test_http_status">HTTP {{ activeConfig.last_test_http_status }} · {{ activeConfig.last_test_latency_ms }}ms</p>
                    <p v-if="activeConfig.last_test_message">{{ activeConfig.last_test_message }}</p>
                </div>

                <form v-if="can_manage_api_config" class="mt-6 space-y-4" @submit.prevent="submitConfig">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Base URL') }}</label>
                            <input v-model="configForm.base_url" type="url" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="https://api.provider.com">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Authentication Type') }}</label>
                            <select v-model="configForm.auth_type" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                <option v-for="authType in api_options.auth_types" :key="authType" :value="authType">
                                    {{ authTypeLabel(authType) }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div v-if="['api_key', 'api_key_secret', 'custom'].includes(configForm.auth_type)">
                            <label class="mb-1.5 block text-sm font-medium">{{ t('API Key') }}</label>
                            <input
                                v-model="configForm.api_key"
                                type="password"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                :placeholder="activeConfig?.has_api_key ? maskedSecret : t('Enter API key')"
                            >
                        </div>
                        <div v-if="['api_key_secret', 'oauth2', 'custom'].includes(configForm.auth_type)">
                            <label class="mb-1.5 block text-sm font-medium">{{ t('API Secret') }}</label>
                            <input
                                v-model="configForm.api_secret"
                                type="password"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                :placeholder="activeConfig?.has_api_secret ? maskedSecret : t('Enter API secret')"
                            >
                        </div>
                        <div v-if="['bearer_token', 'oauth2'].includes(configForm.auth_type)">
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Access Token') }}</label>
                            <input
                                v-model="configForm.access_token"
                                type="password"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                :placeholder="activeConfig?.has_access_token ? maskedSecret : t('Enter access token')"
                            >
                        </div>
                        <div v-if="configForm.auth_type === 'oauth2'">
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Refresh Token') }}</label>
                            <input
                                v-model="configForm.refresh_token"
                                type="password"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                :placeholder="activeConfig?.has_refresh_token ? maskedSecret : t('Enter refresh token')"
                            >
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Webhook URL') }}</label>
                            <input v-model="configForm.webhook_url" type="url" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Timeout (seconds)') }}</label>
                            <input v-model.number="configForm.timeout" type="number" min="1" max="120" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Status') }}</label>
                            <select v-model="configForm.status" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                <option v-for="status in api_options.statuses" :key="status" :value="status">{{ status }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="activeEnvironment === 'production'" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        <label class="flex items-start gap-3">
                            <input v-model="configForm.confirm_production" type="checkbox" class="mt-1">
                            <span>{{ t('I confirm this provider should use the production environment.') }}</span>
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800" :disabled="configForm.processing">
                            {{ t('Save API configuration') }}
                        </button>
                        <button
                            v-if="activeConfig"
                            type="button"
                            class="rounded-xl border border-cyan-300 bg-cyan-50 px-4 py-2.5 text-sm font-medium text-cyan-800"
                            @click="testConnection(activeEnvironment)"
                        >
                            {{ t('Test Connection') }}
                        </button>
                        <button
                            v-if="activeConfig && activeConfig.status === 'active'"
                            type="button"
                            class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-2.5 text-sm font-medium text-rose-800"
                            @click="disableConfig(activeEnvironment)"
                        >
                            {{ t('Disable configuration') }}
                        </button>
                        <button
                            v-if="can_view_credentials && activeConfig"
                            type="button"
                            class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700"
                            @click="auditCredentialAccess(activeEnvironment)"
                        >
                            {{ t('Log credential access') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-950">{{ t('Provider Services') }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ t('Enable the services this provider supports. Integration endpoints remain defined in Laravel code.') }}</p>

                <form class="mt-4 space-y-3" @submit.prevent="submitServices">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">{{ t('Service') }}</th>
                                    <th class="px-4 py-3">{{ t('Enabled') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(service, index) in servicesForm.services" :key="service.service">
                                    <td class="px-4 py-3 font-medium text-slate-900">
                                        {{ services.find((item) => item.service === service.service)?.label || service.service }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="servicesForm.services[index].enabled"
                                            type="checkbox"
                                            :disabled="!can_manage_api_config"
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <button
                        v-if="can_manage_api_config"
                        type="submit"
                        class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                        :disabled="servicesForm.processing"
                    >
                        {{ t('Save services') }}
                    </button>
                </form>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-950">{{ t('API Monitoring') }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ t('Endpoint-level requests, success/error rate, average latency, and last failure.') }}</p>

                <form class="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-6" @submit.prevent="applyLogFilters">
                    <select v-model="logFilterForm.service" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="">{{ t('All services') }}</option>
                        <option v-for="service in api_options.endpoint_catalog.map((x) => x.service).filter((v, i, a) => a.indexOf(v) === i)" :key="service" :value="service">
                            {{ service }}
                        </option>
                    </select>
                    <select v-model="logFilterForm.endpoint" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="">{{ t('All endpoints') }}</option>
                        <option v-for="endpoint in api_options.endpoint_catalog" :key="endpoint.key" :value="endpoint.key">
                            {{ endpoint.label }}
                        </option>
                    </select>
                    <select v-model="logFilterForm.success" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="">{{ t('All statuses') }}</option>
                        <option value="success">{{ t('Success') }}</option>
                        <option value="failed">{{ t('Failed') }}</option>
                    </select>
                    <input v-model="logFilterForm.status_code" type="number" min="100" max="599" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" :placeholder="t('HTTP status')">
                    <input v-model="logFilterForm.date_from" type="date" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <input v-model="logFilterForm.date_to" type="date" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <div class="md:col-span-3 xl:col-span-6 flex gap-2">
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-medium text-white">{{ t('Apply filters') }}</button>
                        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700" @click="resetLogFilters">{{ t('Reset') }}</button>
                    </div>
                </form>

                <div v-if="api_monitoring.length === 0" class="mt-4 text-sm text-slate-500">
                    {{ t('No API metrics yet. Run traffic or connection tests.') }}
                </div>
                <div v-else class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">{{ t('Endpoint') }}</th>
                                <th class="px-4 py-3">{{ t('Requests') }}</th>
                                <th class="px-4 py-3">{{ t('Success Rate') }}</th>
                                <th class="px-4 py-3">{{ t('Error Rate') }}</th>
                                <th class="px-4 py-3">{{ t('Avg Latency') }}</th>
                                <th class="px-4 py-3">{{ t('Last Failure') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="metric in api_monitoring" :key="metric.endpoint_key">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-900">{{ metric.label }}</p>
                                    <p class="text-xs text-slate-500">{{ metric.method }} {{ metric.path }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-800">{{ metric.requests }}</td>
                                <td class="px-4 py-3 text-emerald-700">{{ metric.success_rate }}%</td>
                                <td class="px-4 py-3 text-rose-700">{{ metric.error_rate }}%</td>
                                <td class="px-4 py-3 text-slate-800">{{ metric.average_latency_ms != null ? `${metric.average_latency_ms} ms` : '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p v-if="metric.last_failure">
                                        HTTP {{ metric.last_failure.status_code || '—' }} · {{ formatDateTime(metric.last_failure.occurred_at) }}
                                    </p>
                                    <p v-if="metric.last_failure?.message" class="text-xs text-rose-700">
                                        {{ metric.last_failure.message }}
                                    </p>
                                    <span v-if="!metric.last_failure">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-950">{{ t('API Logs') }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ t('Latest provider API request/response records.') }}</p>

                <div v-if="api_logs.length === 0" class="mt-4 text-sm text-slate-500">
                    {{ t('No API logs captured yet.') }}
                </div>
                <div v-else class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">{{ t('When') }}</th>
                                <th class="px-4 py-3">{{ t('Correlation ID') }}</th>
                                <th class="px-4 py-3">{{ t('Endpoint') }}</th>
                                <th class="px-4 py-3">{{ t('Status') }}</th>
                                <th class="px-4 py-3">{{ t('Response Time') }}</th>
                                <th class="px-4 py-3">{{ t('Details') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="log in api_logs" :key="log.id">
                                <td class="px-4 py-3 text-slate-700">{{ formatDateTime(log.occurred_at) }}</td>
                                <td class="px-4 py-3 text-xs font-mono text-slate-700">{{ log.correlation_id || '—' }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-900">{{ log.endpoint_label }}</p>
                                    <p class="text-xs text-slate-500">{{ log.http_method }} {{ log.endpoint_path }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="log.success ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                    >
                                        {{ log.status_code || (log.success ? 200 : 'failed') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-800">{{ log.response_time_ms != null ? `${log.response_time_ms} ms` : '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p v-if="log.error_message" class="text-rose-700">{{ log.error_message }}</p>
                                    <p v-else>—</p>
                                    <p v-if="log.reference_type" class="text-xs text-slate-500 mt-1">{{ log.reference_type }} #{{ log.reference_id }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold">{{ t('Contacts & contract') }}</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-slate-500">{{ t('Legal name') }}</dt><dd class="font-medium">{{ supplier.legal_name || '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ t('Contact') }}</dt><dd class="font-medium">{{ supplier.contact_name || '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ t('Email') }}</dt><dd class="font-medium">{{ supplier.contact_email || '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ t('Phone') }}</dt><dd class="font-medium">{{ supplier.contact_phone || '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ t('Contract period') }}</dt><dd class="font-medium">{{ supplier.contract_starts_at || '—' }} → {{ supplier.contract_ends_at || '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ t('Website') }}</dt><dd class="font-medium">{{ supplier.website || '—' }}</dd></div>
                    </dl>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold">{{ t('Notes') }}</h3>
                    <p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">{{ supplier.contract_notes || t('No contract notes.') }}</p>
                    <p class="mt-4 whitespace-pre-wrap text-sm text-slate-700">{{ supplier.notes || t('No internal notes.') }}</p>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold">{{ t('Linked wallets') }}</h3>
                <div v-if="!supplier.wallets?.length" class="mt-4 text-sm text-slate-500">{{ t('No wallets linked yet.') }}</div>
                <div v-else class="mt-4 divide-y divide-slate-100">
                    <div v-for="wallet in supplier.wallets" :key="wallet.id" class="flex items-center justify-between py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ wallet.currency }} · {{ wallet.environment }}</p>
                            <p class="text-xs text-slate-500" :class="wallet.is_negative ? 'text-rose-600' : ''">
                                {{ formatMoney(wallet.balance, wallet.currency) }}
                            </p>
                        </div>
                        <Link
                            v-if="can_view_wallets"
                            :href="route('admin.provider-wallets.show', wallet.id)"
                            class="font-medium text-cyan-700"
                        >
                            {{ t('Open') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
