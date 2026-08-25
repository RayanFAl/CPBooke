<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    partner: { type: Object, default: null },
});

const { t, backArrow } = useAdminLocale();
const isEdit = computed(() => Boolean(props.partner?.id));

const form = useForm({
    name: props.partner?.name ?? '',
    slug: props.partner?.slug ?? '',
    status: props.partner?.status ?? 'active',
    contact_email: props.partner?.contact_email ?? '',
    notes: props.partner?.notes ?? '',
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('admin.partners.update', props.partner.id));
        return;
    }

    form.post(route('admin.partners.store'));
};
</script>

<template>
    <AdminLayout>
        <Head :title="isEdit ? t('Edit partner') : t('Add partner')" />

        <section class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-semibold text-slate-950">{{ isEdit ? t('Edit partner') : t('Add partner') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ t('Partners receive order and refund webhooks and can call the Partner API with API keys.') }}</p>
                    </div>
                    <Link :href="route('admin.partners.index')" class="text-sm font-medium text-slate-600 hover:text-slate-950">{{ backArrow }} {{ t('Back') }}</Link>
                </div>
            </div>

            <form class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Name') }}</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required>
                    <p v-if="form.errors.name" class="mt-1 text-sm text-rose-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Slug') }}</label>
                    <input v-model="form.slug" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" :placeholder="t('Optional — auto from name')">
                    <p v-if="form.errors.slug" class="mt-1 text-sm text-rose-600">{{ form.errors.slug }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Status') }}</label>
                        <select v-model="form.status" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <option value="active">{{ t('Active') }}</option>
                            <option value="inactive">{{ t('Inactive') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Contact email') }}</label>
                        <input v-model="form.contact_email" type="email" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <p v-if="form.errors.contact_email" class="mt-1 text-sm text-rose-600">{{ form.errors.contact_email }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Notes') }}</label>
                    <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800" :disabled="form.processing">
                        {{ isEdit ? t('Save') : t('Create partner') }}
                    </button>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
