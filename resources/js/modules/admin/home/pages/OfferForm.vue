<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    offer: { type: Object, default: null },
    options: { type: Object, required: true },
});

const { t } = useAdminLocale();
const isEdit = computed(() => Boolean(props.offer?.id));

const form = useForm({
    title_en: props.offer?.title_en ?? '',
    title_ar: props.offer?.title_ar ?? '',
    subtitle_en: props.offer?.subtitle_en ?? '',
    subtitle_ar: props.offer?.subtitle_ar ?? '',
    badge_en: props.offer?.badge_en ?? '',
    badge_ar: props.offer?.badge_ar ?? '',
    image: null,
    image_url: props.offer?.image_path ? '' : (props.offer?.image_url ?? ''),
    accent_color: props.offer?.accent_color ?? '#5F85C3',
    category: props.offer?.category ?? 'other',
    action_type: props.offer?.action_type ?? 'none',
    action_value: props.offer?.action_value ?? '',
    action_payload: props.offer?.action_payload_json ?? '',
    sort_order: props.offer?.sort_order ?? 0,
    is_active: props.offer?.is_active ?? true,
    starts_at: props.offer?.starts_at ?? '',
    ends_at: props.offer?.ends_at ?? '',
    platforms: props.offer?.platforms ?? [],
});

const needsRouteOrUrl = computed(() => ['route', 'url'].includes(form.action_type));
const needsPayload = computed(() => form.action_type.startsWith('search_'));

const submit = () => {
    const options = { forceFormData: true };

    if (isEdit.value) {
        form.post(route('admin.home.offers.update', props.offer.id), options);
        return;
    }

    form.post(route('admin.home.offers.store'), options);
};

const onImageChange = (event) => {
    form.image = event.target.files?.[0] ?? null;
};
</script>

<template>
    <AdminLayout>
        <Head :title="isEdit ? t('Edit offer') : t('Add offer')" />

        <section class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <Link :href="route('admin.home.index', { tab: 'offers' })" class="text-sm text-cyan-700 hover:underline">
                    ← {{ t('Back to home content') }}
                </Link>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">
                    {{ isEdit ? t('Edit offer') : t('Add offer') }}
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
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Badge (EN)') }}</label>
                        <input v-model="form.badge_en" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="20% OFF">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Badge (AR)') }}</label>
                        <input v-model="form.badge_ar" type="text" dir="rtl" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Upload image') }}</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="w-full text-sm" @change="onImageChange">
                        <p class="mt-1 text-xs text-slate-500">{{ t('Prefer WebP/JPG ~800px wide.') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Or CDN image URL') }}</label>
                        <input v-model="form.image_url" type="url" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                </div>

                <div v-if="offer?.image_url" class="rounded-xl border border-slate-200 p-3">
                    <p class="mb-2 text-xs text-slate-500">{{ t('Current image') }}</p>
                    <img :src="offer.image_url" alt="" class="h-24 rounded-lg object-cover">
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Category') }}</label>
                        <select v-model="form.category" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <option v-for="category in options.categories" :key="category" :value="category">{{ category }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Accent color') }}</label>
                        <input v-model="form.accent_color" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="#5F85C3">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Action type') }}</label>
                        <select v-model="form.action_type" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <option v-for="type in options.action_types" :key="type" :value="type">{{ type }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="needsRouteOrUrl">
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Action value') }}</label>
                    <input v-model="form.action_value" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </div>

                <div v-if="needsPayload">
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Action payload (JSON)') }}</label>
                    <textarea
                        v-model="form.action_payload"
                        rows="4"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm"
                        placeholder='{"origin":"MJI","destination":"IST","trip_type":"oneWay","depart_date":"2026-09-15","adults":1,"travel_class":"Economy"}'
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
                    <Link :href="route('admin.home.index', { tab: 'offers' })" class="rounded-xl px-4 py-2.5 text-sm text-slate-600">
                        {{ t('Cancel') }}
                    </Link>
                    <button
                        type="submit"
                        class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ isEdit ? t('Save offer') : t('Create offer') }}
                    </button>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
