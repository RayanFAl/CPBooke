<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    banner: { type: Object, default: null },
    options: { type: Object, required: true },
});

const { t } = useAdminLocale();
const isEdit = computed(() => Boolean(props.banner?.id));

const form = useForm({
    title_en: props.banner?.title_en ?? '',
    title_ar: props.banner?.title_ar ?? '',
    subtitle_en: props.banner?.subtitle_en ?? '',
    subtitle_ar: props.banner?.subtitle_ar ?? '',
    image: null,
    image_url: props.banner?.image_path ? '' : (props.banner?.image_url ?? ''),
    action_type: props.banner?.action_type ?? 'none',
    action_value: props.banner?.action_value ?? '',
    action_payload: props.banner?.action_payload_json ?? '',
    sort_order: props.banner?.sort_order ?? 0,
    is_active: props.banner?.is_active ?? true,
    starts_at: props.banner?.starts_at ?? '',
    ends_at: props.banner?.ends_at ?? '',
    platforms: props.banner?.platforms ?? [],
});

const needsRouteOrUrl = computed(() => ['route', 'url'].includes(form.action_type));
const needsPayload = computed(() => form.action_type.startsWith('search_'));

const submit = () => {
    const options = { forceFormData: true };

    if (isEdit.value) {
        form.post(route('admin.home.banners.update', props.banner.id), options);
        return;
    }

    form.post(route('admin.home.banners.store'), options);
};

const onImageChange = (event) => {
    form.image = event.target.files?.[0] ?? null;
};
</script>

<template>
    <AdminLayout>
        <Head :title="isEdit ? t('Edit banner') : t('Add banner')" />

        <section class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <Link :href="route('admin.home.index', { tab: 'banners' })" class="text-sm text-cyan-700 hover:underline">
                    ← {{ t('Back to home content') }}
                </Link>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">
                    {{ isEdit ? t('Edit banner') : t('Add banner') }}
                </h2>
            </div>

            <form class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Title (EN)') }}</label>
                        <input v-model="form.title_en" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required>
                        <p v-if="form.errors.title_en" class="mt-1 text-sm text-rose-600">{{ form.errors.title_en }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Title (AR)') }}</label>
                        <input v-model="form.title_ar" type="text" dir="rtl" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Subtitle (EN)') }}</label>
                        <input v-model="form.subtitle_en" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Subtitle (AR)') }}</label>
                        <input v-model="form.subtitle_ar" type="text" dir="rtl" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Upload image') }}</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="w-full text-sm" @change="onImageChange">
                        <p class="mt-1 text-xs text-slate-500">{{ t('Prefer WebP/JPG ~1600px wide.') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Or CDN image URL') }}</label>
                        <input v-model="form.image_url" type="url" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="https://cdn.example.com/...">
                    </div>
                </div>

                <div v-if="banner?.image_url" class="rounded-xl border border-slate-200 p-3">
                    <p class="mb-2 text-xs text-slate-500">{{ t('Current image') }}</p>
                    <img :src="banner.image_url" alt="" class="h-24 rounded-lg object-cover">
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Action type') }}</label>
                        <select v-model="form.action_type" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <option v-for="type in options.action_types" :key="type" :value="type">{{ type }}</option>
                        </select>
                    </div>
                    <div v-if="needsRouteOrUrl">
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Action value') }}</label>
                        <input v-model="form.action_value" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="/esim-offer or https://...">
                    </div>
                </div>

                <div v-if="needsPayload">
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Action payload (JSON)') }}</label>
                    <textarea
                        v-model="form.action_payload"
                        rows="4"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm"
                        placeholder='{"origin":"BAH","destination":"IST"}'
                    />
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Sort order') }}</label>
                        <input v-model="form.sort_order" type="number" min="0" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Starts at') }}</label>
                        <input v-model="form.starts_at" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Ends at') }}</label>
                        <input v-model="form.ends_at" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300">
                        {{ t('Active') }}
                    </label>
                    <label v-for="platform in options.platforms" :key="platform" class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.platforms" type="checkbox" :value="platform" class="rounded border-slate-300">
                        {{ platform }}
                    </label>
                    <p class="text-xs text-slate-500">{{ t('Leave platforms unchecked for all devices.') }}</p>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Link :href="route('admin.home.index', { tab: 'banners' })" class="rounded-xl px-4 py-2.5 text-sm text-slate-600">
                        {{ t('Cancel') }}
                    </Link>
                    <button
                        type="submit"
                        class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ isEdit ? t('Save banner') : t('Create banner') }}
                    </button>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
