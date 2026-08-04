<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import HomeActionPayloadEditor from '../components/HomeActionPayloadEditor.vue';
import HomeImagePicker from '../components/HomeImagePicker.vue';
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

const actionTypeLabel = (type) => t(`home.action.${type}`);

const actionTypeHint = computed(() => t(`home.action.hint.${form.action_type}`));

const actionValueLabel = computed(() => (
    form.action_type === 'url'
        ? t('External link (URL)')
        : t('App screen path')
));

const actionValuePlaceholder = computed(() => (
    form.action_type === 'url'
        ? 'https://example.com/offer'
        : '/offers'
));

const fillActionValueExample = () => {
    if (!form.action_value) {
        form.action_value = actionValuePlaceholder.value;
    }
};

const submit = () => {
    const options = { forceFormData: true };

    if (isEdit.value) {
        form.post(route('admin.home.offers.update', props.offer.id), options);
        return;
    }

    form.post(route('admin.home.offers.store'), options);
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

                <HomeImagePicker
                    v-model:image="form.image"
                    v-model:image-url="form.image_url"
                    :current-image-url="offer?.image_url || ''"
                    :hint="t('Prefer WebP/JPG ~800px wide.')"
                    :error="form.errors.image || form.errors.image_url || ''"
                />

                <div class="grid gap-4 md:grid-cols-2">
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
                </div>

                <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('When user taps the offer') }}</label>
                        <select v-model="form.action_type" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            <option v-for="type in options.action_types" :key="type" :value="type">
                                {{ actionTypeLabel(type) }}
                            </option>
                        </select>
                        <p class="mt-2 text-xs leading-5 text-slate-600">{{ actionTypeHint }}</p>
                    </div>

                    <div v-if="needsRouteOrUrl">
                        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                            <label class="block text-sm font-medium">{{ actionValueLabel }}</label>
                            <button
                                type="button"
                                class="text-xs font-medium text-cyan-700 hover:underline"
                                @click="fillActionValueExample"
                            >
                                {{ t('Insert example') }}
                            </button>
                        </div>
                        <input
                            v-model="form.action_value"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"
                            :placeholder="actionValuePlaceholder"
                        >
                        <p class="mt-1 text-xs text-slate-500">
                            {{ form.action_type === 'url'
                                ? t('Paste a full web link starting with https://')
                                : t('Ask the mobile team for the exact screen path.') }}
                        </p>
                    </div>

                    <HomeActionPayloadEditor
                        v-if="needsPayload"
                        v-model="form.action_payload"
                        :action-type="form.action_type"
                        :error="form.errors.action_payload || ''"
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
                <p class="text-xs text-slate-500">
                    {{ t('Schedule times use platform timezone') }}:
                    <span class="font-medium text-slate-700">{{ options.schedule_timezone || offer?.schedule_timezone || 'Africa/Tripoli' }}</span>.
                    {{ t('Leave Starts at empty to publish immediately.') }}
                </p>

                <div class="space-y-3">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300">
                        {{ t('Active') }}
                    </label>

                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <span class="text-sm font-medium text-slate-800">{{ t('Platforms') }}</span>
                        <label
                            v-for="platform in options.platforms"
                            :key="platform"
                            class="inline-flex items-center gap-2 rounded-lg bg-white px-2.5 py-1 text-sm capitalize text-slate-700 ring-1 ring-slate-200"
                        >
                            <input v-model="form.platforms" type="checkbox" :value="platform" class="rounded border-slate-300">
                            {{ platform }}
                        </label>
                        <span class="text-xs text-slate-500">
                            {{ t('Leave platforms unchecked for all devices.') }}
                        </span>
                    </div>
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
