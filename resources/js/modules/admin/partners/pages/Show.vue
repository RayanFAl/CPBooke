<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    partner: { type: Object, required: true },
    api_keys: { type: Array, default: () => [] },
    webhooks: { type: Array, default: () => [] },
    deliveries: { type: Array, default: () => [] },
    webhook_events: { type: Array, default: () => [] },
    can_manage: { type: Boolean, default: false },
    created_api_key: { type: Object, default: null },
    created_webhook_secret: { type: Object, default: null },
});

const { t } = useAdminLocale();

const keyForm = useForm({ name: '' });
const webhookForm = useForm({
    url: '',
    events: [...props.webhook_events],
    description: '',
    is_active: true,
});

const editingWebhookId = ref(null);
const editWebhookForm = useForm({
    url: '',
    events: [],
    description: '',
    is_active: true,
});

const createKey = () => {
    keyForm.post(route('admin.partners.api-keys.store', props.partner.id), {
        preserveScroll: true,
        onSuccess: () => keyForm.reset('name'),
    });
};

const revokeKey = (key) => {
    if (!confirm(t('Revoke this API key? Existing integrations using it will stop working.'))) {
        return;
    }

    router.post(route('admin.partners.api-keys.revoke', [props.partner.id, key.id]), {}, { preserveScroll: true });
};

const createWebhook = () => {
    webhookForm.post(route('admin.partners.webhooks.store', props.partner.id), {
        preserveScroll: true,
        onSuccess: () => {
            webhookForm.reset();
            webhookForm.events = [...props.webhook_events];
            webhookForm.is_active = true;
        },
    });
};

const startEditWebhook = (webhook) => {
    editingWebhookId.value = webhook.id;
    editWebhookForm.url = webhook.url;
    editWebhookForm.events = [...(webhook.events ?? [])];
    editWebhookForm.description = webhook.description ?? '';
    editWebhookForm.is_active = Boolean(webhook.is_active);
};

const saveWebhook = () => {
    if (!editingWebhookId.value) {
        return;
    }

    editWebhookForm.put(route('admin.partners.webhooks.update', [props.partner.id, editingWebhookId.value]), {
        preserveScroll: true,
        onSuccess: () => {
            editingWebhookId.value = null;
        },
    });
};

const deleteWebhook = (webhook) => {
    if (!confirm(t('Delete this webhook endpoint?'))) {
        return;
    }

    router.delete(route('admin.partners.webhooks.destroy', [props.partner.id, webhook.id]), { preserveScroll: true });
};
</script>

<template>
    <AdminLayout>
        <Head :title="partner.name" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Partner') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ partner.name }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ partner.slug }} · {{ partner.status }}</p>
                        <p v-if="partner.contact_email" class="mt-1 text-sm text-slate-500">{{ partner.contact_email }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('admin.partners.index')" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            {{ t('Back') }}
                        </Link>
                        <Link
                            v-if="can_manage"
                            :href="route('admin.partners.edit', partner.id)"
                            class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                        >
                            {{ t('Edit') }}
                        </Link>
                    </div>
                </div>
                <p v-if="partner.notes" class="mt-4 text-sm leading-6 text-slate-600">{{ partner.notes }}</p>
            </div>

            <div
                v-if="created_api_key"
                class="rounded-3xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950"
            >
                <p class="font-semibold">{{ t('Copy the API key now — it will not be shown again.') }}</p>
                <code class="mt-2 block break-all rounded-xl bg-white px-3 py-2 font-mono text-xs">{{ created_api_key.plain_text }}</code>
            </div>

            <div
                v-if="created_webhook_secret"
                class="rounded-3xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950"
            >
                <p class="font-semibold">{{ t('Copy the webhook signing secret now — it will not be shown again.') }}</p>
                <code class="mt-2 block break-all rounded-xl bg-white px-3 py-2 font-mono text-xs">{{ created_webhook_secret.signing_secret }}</code>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <div class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('API keys') }}</h3>
                    <p class="text-sm text-slate-600">{{ t('Use Authorization: Bearer pk_live_… or X-Partner-Key on /api/v1/partner/*') }}</p>

                    <form v-if="can_manage" class="flex flex-col gap-2 sm:flex-row" @submit.prevent="createKey">
                        <input
                            v-model="keyForm.name"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            :placeholder="t('Key label')"
                            required
                        >
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800" :disabled="keyForm.processing">
                            {{ t('Create key') }}
                        </button>
                    </form>
                    <p v-if="keyForm.errors.name" class="text-sm text-rose-600">{{ keyForm.errors.name }}</p>

                    <ul class="divide-y divide-slate-100">
                        <li v-for="key in api_keys" :key="key.id" class="flex items-start justify-between gap-3 py-3 text-sm">
                            <div>
                                <p class="font-medium text-slate-950">{{ key.name }}</p>
                                <p class="font-mono text-xs text-slate-500">{{ key.key_prefix }}…</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ key.is_active ? t('Active') : t('Revoked') }}
                                    <span v-if="key.last_used_at"> · {{ t('Last used') }}: {{ key.last_used_at }}</span>
                                </p>
                            </div>
                            <button
                                v-if="can_manage && key.is_active"
                                type="button"
                                class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50"
                                @click="revokeKey(key)"
                            >
                                {{ t('Revoke') }}
                            </button>
                        </li>
                        <li v-if="!api_keys.length" class="py-4 text-sm text-slate-500">{{ t('No API keys yet.') }}</li>
                    </ul>
                </div>

                <div class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Webhooks') }}</h3>
                    <p class="text-sm text-slate-600">{{ t('Signed POST callbacks for order.created, order.confirmed, payment.succeeded, refund.issued.') }}</p>

                    <form v-if="can_manage" class="space-y-3" @submit.prevent="createWebhook">
                        <input v-model="webhookForm.url" type="url" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" :placeholder="t('https://partner.example/webhooks/cpbooke')" required>
                        <input v-model="webhookForm.description" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" :placeholder="t('Description (optional)')">
                        <div class="flex flex-wrap gap-3">
                            <label v-for="eventName in webhook_events" :key="eventName" class="inline-flex items-center gap-2 text-xs text-slate-700">
                                <input v-model="webhookForm.events" type="checkbox" :value="eventName" class="rounded border-slate-300">
                                {{ eventName }}
                            </label>
                        </div>
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800" :disabled="webhookForm.processing">
                            {{ t('Add endpoint') }}
                        </button>
                        <p v-if="webhookForm.errors.url" class="text-sm text-rose-600">{{ webhookForm.errors.url }}</p>
                        <p v-if="webhookForm.errors.events" class="text-sm text-rose-600">{{ webhookForm.errors.events }}</p>
                    </form>

                    <ul class="divide-y divide-slate-100">
                        <li v-for="webhook in webhooks" :key="webhook.id" class="space-y-3 py-4 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="break-all font-medium text-slate-950">{{ webhook.url }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ webhook.is_active ? t('Active') : t('Paused') }}
                                        · {{ (webhook.events || []).join(', ') }}
                                    </p>
                                    <p v-if="webhook.description" class="mt-1 text-xs text-slate-500">{{ webhook.description }}</p>
                                </div>
                                <div v-if="can_manage" class="flex gap-2">
                                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50" @click="startEditWebhook(webhook)">
                                        {{ t('Edit') }}
                                    </button>
                                    <button type="button" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50" @click="deleteWebhook(webhook)">
                                        {{ t('Delete') }}
                                    </button>
                                </div>
                            </div>

                            <form v-if="can_manage && editingWebhookId === webhook.id" class="space-y-3 rounded-2xl bg-slate-50 p-4" @submit.prevent="saveWebhook">
                                <input v-model="editWebhookForm.url" type="url" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required>
                                <div class="flex flex-wrap gap-3">
                                    <label v-for="eventName in webhook_events" :key="`edit-${eventName}`" class="inline-flex items-center gap-2 text-xs text-slate-700">
                                        <input v-model="editWebhookForm.events" type="checkbox" :value="eventName" class="rounded border-slate-300">
                                        {{ eventName }}
                                    </label>
                                </div>
                                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                    <input v-model="editWebhookForm.is_active" type="checkbox" class="rounded border-slate-300">
                                    {{ t('Active') }}
                                </label>
                                <div class="flex gap-2">
                                    <button type="submit" class="rounded-xl bg-slate-950 px-3 py-2 text-xs font-medium text-white">{{ t('Save') }}</button>
                                    <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-xs" @click="editingWebhookId = null">{{ t('Cancel') }}</button>
                                </div>
                            </form>
                        </li>
                        <li v-if="!webhooks.length" class="py-4 text-sm text-slate-500">{{ t('No webhook endpoints yet.') }}</li>
                    </ul>
                </div>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Recent deliveries') }}</h3>
                </div>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('Event') }}</th>
                            <th class="px-4 py-3">{{ t('Status') }}</th>
                            <th class="px-4 py-3">{{ t('HTTP') }}</th>
                            <th class="px-4 py-3">{{ t('Attempts') }}</th>
                            <th class="px-4 py-3">{{ t('Created') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="delivery in deliveries" :key="delivery.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-950">{{ delivery.event }}</p>
                                <p class="truncate text-xs text-slate-500">{{ delivery.endpoint_url }}</p>
                            </td>
                            <td class="px-4 py-3">{{ delivery.status }}</td>
                            <td class="px-4 py-3">{{ delivery.response_code ?? '—' }}</td>
                            <td class="px-4 py-3">{{ delivery.attempt_count }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ delivery.created_at }}</td>
                        </tr>
                        <tr v-if="!deliveries.length">
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ t('No deliveries yet.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AdminLayout>
</template>
