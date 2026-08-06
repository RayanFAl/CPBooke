<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    page: { type: Object, default: null },
});

const { t } = useAdminLocale();
const isEdit = computed(() => Boolean(props.page?.id));

const form = useForm({
    slug: props.page?.slug ?? '',
    title_en: props.page?.title_en ?? '',
    title_ar: props.page?.title_ar ?? '',
    body_en: props.page?.body_en ?? '',
    body_ar: props.page?.body_ar ?? '',
    sort_order: props.page?.sort_order ?? 0,
    is_active: props.page?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('admin.content.update', props.page.id));
        return;
    }

    form.post(route('admin.content.store'));
};
</script>

<template>
    <AdminLayout>
        <Head :title="isEdit ? t('Edit page') : t('Add page')" />

        <section class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <Link :href="route('admin.content.index')" class="text-sm text-cyan-700 hover:underline">
                    ← {{ t('Back to content pages') }}
                </Link>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">
                    {{ isEdit ? t('Edit page') : t('Add page') }}
                </h2>
                <p class="mt-2 text-sm text-slate-600">
                    {{ t('Use a stable slug for the mobile app, e.g. privacy-policy or terms-of-service.') }}
                </p>
            </div>

            <form class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Slug') }}</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm"
                            placeholder="privacy-policy"
                            required
                        >
                        <p v-if="form.errors.slug" class="mt-1 text-sm text-rose-600">{{ form.errors.slug }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Sort') }}</label>
                        <input v-model="form.sort_order" type="number" min="0" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <p v-if="form.errors.sort_order" class="mt-1 text-sm text-rose-600">{{ form.errors.sort_order }}</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Title (EN)') }}</label>
                        <input v-model="form.title_en" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required>
                        <p v-if="form.errors.title_en" class="mt-1 text-sm text-rose-600">{{ form.errors.title_en }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Title (AR)') }}</label>
                        <input v-model="form.title_ar" type="text" dir="rtl" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <p v-if="form.errors.title_ar" class="mt-1 text-sm text-rose-600">{{ form.errors.title_ar }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Body (EN)') }}</label>
                    <textarea
                        v-model="form.body_en"
                        rows="12"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm leading-6"
                        required
                    />
                    <p v-if="form.errors.body_en" class="mt-1 text-sm text-rose-600">{{ form.errors.body_en }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Body (AR)') }}</label>
                    <textarea
                        v-model="form.body_ar"
                        rows="12"
                        dir="rtl"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm leading-6"
                    />
                    <p v-if="form.errors.body_ar" class="mt-1 text-sm text-rose-600">{{ form.errors.body_ar }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300">
                    {{ t('Active') }}
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <Link
                        :href="route('admin.content.index')"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700"
                    >
                        {{ t('Cancel') }}
                    </Link>
                    <button
                        type="submit"
                        class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ isEdit ? t('Save changes') : t('Create page') }}
                    </button>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
