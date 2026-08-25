<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    integration: { type: Object, required: true },
    models: { type: Array, default: () => [] },
    can_toggle_enabled: { type: Boolean, default: false },
    update_url: { type: String, required: true },
    test_url: { type: String, required: true },
});

const { t } = useAdminLocale();
const page = usePage();
const testing = ref(false);
const testResult = ref(null);

const form = useForm({
    enabled: Boolean(props.settings.enabled),
    provider: props.settings.provider ?? 'gemini',
    model: props.settings.model ?? 'gemini-flash-lite-latest',
    base_url: props.settings.base_url ?? 'https://generativelanguage.googleapis.com/v1beta',
    timeout: Number(props.settings.timeout ?? 12),
    max_output_tokens: Number(props.settings.max_output_tokens ?? 1024),
    temperature: Number(props.settings.temperature ?? 0.2),
    max_offers_for_recommendation: Number(props.settings.max_offers_for_recommendation ?? 8),
    max_conversation_turns: Number(props.settings.max_conversation_turns ?? 6),
    timezone: props.settings.timezone ?? 'Africa/Tripoli',
    default_currency: props.settings.default_currency ?? 'LYD',
    prefer_rules_nlu: Boolean(props.settings.prefer_rules_nlu ?? true),
});

const flashSuccess = computed(() => page.props.flash?.success ?? '');

const modeBadgeClass = (mode) => {
    if (mode === 'configured') {
        return 'bg-emerald-100 text-emerald-800';
    }

    if (mode === 'disabled') {
        return 'bg-slate-200 text-slate-700';
    }

    return 'bg-rose-100 text-rose-800';
};

const submit = () => {
    form.put(props.update_url, {
        preserveScroll: true,
    });
};

const testConnection = async () => {
    testing.value = true;
    testResult.value = null;

    try {
        const response = await fetch(props.test_url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': page.props.csrf_token ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const payload = await response.json();
        testResult.value = {
            ok: response.ok && payload.success,
            message: payload.message ?? (response.ok ? 'OK' : 'Connection failed'),
            reason: payload.reason ?? null,
            model: payload.model ?? null,
        };
    } catch (error) {
        testResult.value = {
            ok: false,
            message: error?.message ?? 'Connection test failed',
        };
    } finally {
        testing.value = false;
    }
};
</script>

<template>
    <AdminLayout
        title="AI Travel Assistant"
        description="Control Gemini NLU and recommendation settings for BookNow Voice Assistant."
    >
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Platform') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('AI Travel Assistant') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {{ t('Runtime toggles are saved here. GEMINI_API_KEY stays in .env only — never in the database or mobile app.') }}
                </p>
                <p v-if="flashSuccess" class="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ flashSuccess }}
                </p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ integration.provider }} · {{ integration.model }}</p>
                        <p class="text-xs text-slate-500">{{ integration.env_key }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="integration.enabled ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-600'"
                        >
                            {{ integration.enabled ? t('Enabled') : t('Disabled') }}
                        </span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="modeBadgeClass(integration.mode)">
                            {{ integration.mode }}
                        </span>
                        <span v-if="integration.api_key_hint" class="text-xs text-slate-500">{{ integration.api_key_hint }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 disabled:opacity-60"
                        :disabled="testing"
                        @click="testConnection"
                    >
                        {{ testing ? t('Testing…') : t('Test Gemini connection') }}
                    </button>
                    <a
                        :href="route('admin.ai.logs.index')"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
                    >
                        {{ t('View request logs') }}
                    </a>
                </div>
                <p
                    v-if="testResult"
                    class="mt-3 rounded-2xl px-4 py-3 text-sm"
                    :class="testResult.ok ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-700'"
                >
                    {{ testResult.message }}
                    <span v-if="testResult.model" class="block text-xs opacity-80">{{ testResult.model }}</span>
                </p>
            </div>

            <form class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="flex items-center gap-3 text-sm text-slate-800 md:col-span-2">
                        <input
                            v-model="form.enabled"
                            type="checkbox"
                            class="rounded border-slate-300"
                            :disabled="!can_toggle_enabled"
                        />
                        {{ t('AI travel assistant enabled') }}
                        <span v-if="!can_toggle_enabled" class="text-xs text-slate-500">({{ t('super admin only') }})</span>
                    </label>

                    <label class="flex items-center gap-3 text-sm text-slate-800 md:col-span-2">
                        <input v-model="form.prefer_rules_nlu" type="checkbox" class="rounded border-slate-300" />
                        {{ t('Prefer Rules NLU for simple requests (Free Tier saver)') }}
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Provider') }}</span>
                        <select v-model="form.provider" class="w-full rounded-2xl border-slate-200">
                            <option value="gemini">Gemini</option>
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Model') }}</span>
                        <select v-model="form.model" class="w-full rounded-2xl border-slate-200">
                            <option v-for="model in models" :key="model" :value="model">{{ model }}</option>
                        </select>
                    </label>

                    <label class="block text-sm md:col-span-2">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Gemini base URL') }}</span>
                        <input v-model="form.base_url" type="url" class="w-full rounded-2xl border-slate-200" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Timeout (seconds)') }}</span>
                        <input v-model.number="form.timeout" type="number" min="3" max="60" class="w-full rounded-2xl border-slate-200" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Temperature') }}</span>
                        <input v-model.number="form.temperature" type="number" min="0" max="2" step="0.1" class="w-full rounded-2xl border-slate-200" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Max output tokens') }}</span>
                        <input v-model.number="form.max_output_tokens" type="number" min="128" max="8192" class="w-full rounded-2xl border-slate-200" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Max offers for recommendation') }}</span>
                        <input v-model.number="form.max_offers_for_recommendation" type="number" min="1" max="20" class="w-full rounded-2xl border-slate-200" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Max conversation turns') }}</span>
                        <input v-model.number="form.max_conversation_turns" type="number" min="0" max="20" class="w-full rounded-2xl border-slate-200" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Timezone') }}</span>
                        <input v-model="form.timezone" type="text" class="w-full rounded-2xl border-slate-200" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Default currency') }}</span>
                        <input v-model="form.default_currency" type="text" maxlength="3" class="w-full rounded-2xl border-slate-200 uppercase" />
                    </label>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="submit"
                        class="rounded-2xl bg-slate-950 px-5 py-2.5 text-sm font-medium text-white disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? t('Saving…') : t('Save AI settings') }}
                    </button>
                </div>

                <div v-if="Object.keys(form.errors).length" class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
