<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    page: { type: Object, default: null },
    options: { type: Object, required: true },
});

const { t, isArabic } = useAdminLocale();
const isEdit = computed(() => Boolean(props.page?.id));

const form = useForm({
    slug: props.page?.slug ?? '',
    category: props.page?.category ?? 'legal',
    product: props.page?.product ?? '',
    title_en: props.page?.title_en ?? '',
    title_ar: props.page?.title_ar ?? '',
    body_en: props.page?.body_en ?? '',
    body_ar: props.page?.body_ar ?? '',
    url: props.page?.url ?? '',
    sort_order: props.page?.sort_order ?? 0,
    is_active: props.page?.is_active ?? true,
});

const isProductPolicy = computed(() => form.category === 'product_policy');

const optionLabel = (item) => (isArabic.value ? (item.label_ar || item.label) : item.label);

const recommendedSlug = (product) => {
    const match = (props.options.products ?? []).find((item) => item.value === product);

    return match?.slug ?? '';
};

watch(() => form.category, (category) => {
    if (category !== 'product_policy') {
        form.product = '';
        return;
    }

    if (form.product) {
        form.slug = recommendedSlug(form.product);
    }
});

watch(() => form.product, (product) => {
    if (!isProductPolicy.value || !product) {
        return;
    }

    form.slug = recommendedSlug(product);
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
                    {{ t('Legal pages appear in app settings and checkout. Product policies are company text shown next to provider fare rules — they do not replace them.') }}
                </p>
            </div>

            <form class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Category') }}</label>
                        <select v-model="form.category" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <option v-for="category in options.categories" :key="category.value" :value="category.value">
                                {{ optionLabel(category) }}
                            </option>
                        </select>
                        <p v-if="form.errors.category" class="mt-1 text-sm text-rose-600">{{ form.errors.category }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Product') }}</label>
                        <select
                            v-model="form.product"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm disabled:bg-slate-50"
                            :required="isProductPolicy"
                            :disabled="!isProductPolicy"
                        >
                            <option value="">{{ isProductPolicy ? t('Select a product') : t('Not applicable') }}</option>
                            <option v-for="product in options.products" :key="product.value" :value="product.value">
                                {{ optionLabel(product) }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ isProductPolicy ? t('One policy page per product (flight, hotel, insurance, eSIM).') : t('Leave empty for privacy and terms pages.') }}
                        </p>
                        <p v-if="form.errors.product" class="mt-1 text-sm text-rose-600">{{ form.errors.product }}</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Slug') }}</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm disabled:bg-slate-50"
                            placeholder="privacy-policy"
                            required
                            :readonly="isProductPolicy"
                        >
                        <p class="mt-1 text-xs text-slate-500">
                            {{ isProductPolicy
                                ? t('Set automatically from the product, e.g. flight-policy.')
                                : t('Use a stable slug for the mobile app, e.g. privacy-policy or terms-of-service.') }}
                        </p>
                        <p v-if="form.errors.slug" class="mt-1 text-sm text-rose-600">{{ form.errors.slug }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Sort') }}</label>
                        <input v-model="form.sort_order" type="number" min="0" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <p v-if="form.errors.sort_order" class="mt-1 text-sm text-rose-600">{{ form.errors.sort_order }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Public URL') }}</label>
                    <input
                        v-model="form.url"
                        type="url"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        placeholder="https://"
                    >
                    <p class="mt-1 text-xs text-slate-500">
                        {{ t('Optional. If set to an https:// link, the app opens it instead of the HTML body.') }}
                    </p>
                    <p v-if="form.errors.url" class="mt-1 text-sm text-rose-600">{{ form.errors.url }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Title (EN)') }}</label>
                        <input v-model="form.title_en" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required>
                        <p v-if="form.errors.title_en" class="mt-1 text-sm text-rose-600">{{ form.errors.title_en }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Title (AR)') }}</label>
                        <input v-model="form.title_ar" type="text" dir="rtl" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required>
                        <p v-if="form.errors.title_ar" class="mt-1 text-sm text-rose-600">{{ form.errors.title_ar }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Body (EN)') }}</label>
                    <textarea
                        v-model="form.body_en"
                        rows="12"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm leading-6"
                        required
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        {{ t('HTML shown in the app (headings, lists, links).') }}
                    </p>
                    <p v-if="form.errors.body_en" class="mt-1 text-sm text-rose-600">{{ form.errors.body_en }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Body (AR)') }}</label>
                    <textarea
                        v-model="form.body_ar"
                        rows="12"
                        dir="rtl"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm leading-6"
                        required
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        {{ t('HTML shown in the app (headings, lists, links).') }}
                    </p>
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
