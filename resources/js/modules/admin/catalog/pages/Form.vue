<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import HomeActionPayloadEditor from '../../home/components/HomeActionPayloadEditor.vue';
import HomeImagePicker from '../../home/components/HomeImagePicker.vue';
import { actionValueExample, KNOWN_ROUTE_EXAMPLES } from '../../home/utils/mobileActionExamples';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    type: { type: Object, default: null },
    options: { type: Object, required: true },
});

const { t, backArrow } = useAdminLocale();
const isEdit = computed(() => Boolean(props.type?.id));

const form = useForm({
    key: props.type?.key ?? '',
    title_en: props.type?.title_en ?? '',
    title_ar: props.type?.title_ar ?? '',
    subtitle_en: props.type?.subtitle_en ?? '',
    subtitle_ar: props.type?.subtitle_ar ?? '',
    options_image: null,
    options_image_url: props.type?.options_image_path ? '' : (props.type?.options_image_url ?? ''),
    market_image: null,
    market_image_url: props.type?.market_image_path ? '' : (props.type?.market_image_url ?? ''),
    show_in_options: props.type?.show_in_options ?? true,
    show_in_market: props.type?.show_in_market ?? true,
    action_type: props.type?.action_type ?? 'route',
    action_value: props.type?.action_value ?? '',
    action_payload: props.type?.action_payload_json ?? '',
    sort_order: props.type?.sort_order ?? 0,
    is_active: props.type?.is_active ?? true,
    platforms: props.type?.platforms ?? [],
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

const actionValuePlaceholder = computed(() => actionValueExample({
    actionType: form.action_type,
    catalogKey: form.key,
}));

const knownPathsHint = computed(() => KNOWN_ROUTE_EXAMPLES.join(' · '));

const fillActionValueExample = () => {
    form.action_value = actionValuePlaceholder.value;
};

const submit = () => {
    const options = { forceFormData: true };

    if (isEdit.value) {
        form.post(route('admin.catalog.update', props.type.id), options);
        return;
    }

    form.post(route('admin.catalog.store'), options);
};
</script>

<template>
    <AdminLayout>
        <Head :title="isEdit ? t('Edit type') : t('Add type')" />

        <section class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <Link :href="route('admin.catalog.index')" class="text-sm text-cyan-700 hover:underline">
                    {{ backArrow }} {{ t('Back to Options & Market') }}
                </Link>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">
                    {{ isEdit ? t('Edit type') : t('Add type') }}
                </h2>
                <p class="mt-2 text-sm text-slate-600">
                    {{ t('Each type appears on the mobile Options and Market screens with its own image.') }}
                </p>
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

                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Type key') }}</label>
                    <input
                        v-model="form.key"
                        type="text"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        placeholder="travel-insurance"
                    >
                    <p class="mt-1 text-xs text-slate-500">{{ t('Lowercase slug used by the mobile app. Leave empty to generate from the English title.') }}</p>
                    <p v-if="form.errors.key" class="mt-1 text-sm text-rose-600">{{ form.errors.key }}</p>
                </div>

                <HomeImagePicker
                    v-model:image="form.options_image"
                    v-model:image-url="form.options_image_url"
                    :label="t('Options image')"
                    :current-image-url="type?.options_image_url || ''"
                    :hint="t('Shown on the Options screen. Prefer WebP/JPG ~800px wide.')"
                    :error="form.errors.options_image || form.errors.options_image_url || ''"
                />

                <HomeImagePicker
                    v-model:image="form.market_image"
                    v-model:image-url="form.market_image_url"
                    :label="t('Market image')"
                    :current-image-url="type?.market_image_url || ''"
                    :hint="t('Shown on the Market screen. Prefer WebP/JPG ~1200px wide.')"
                    :error="form.errors.market_image || form.errors.market_image_url || ''"
                />

                <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('When user taps the card') }}</label>
                        <select v-model="form.action_type" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            <option v-for="actionType in options.action_types" :key="actionType" :value="actionType">
                                {{ actionTypeLabel(actionType) }}
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
                                : t('Suggested path for this type') + ': ' + actionValuePlaceholder }}
                        </p>
                        <p v-if="form.action_type === 'route'" class="mt-1 text-xs text-slate-400">
                            {{ t('Known app paths') }}: {{ knownPathsHint }}
                        </p>
                    </div>

                    <HomeActionPayloadEditor
                        v-if="needsPayload"
                        v-model="form.action_payload"
                        :action-type="form.action_type"
                        :error="form.errors.action_payload || ''"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Sort order') }}</label>
                    <input v-model="form.sort_order" type="number" min="0" class="w-full max-w-xs rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </div>

                <div class="space-y-3">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300">
                        {{ t('Active') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.show_in_options" type="checkbox" class="rounded border-slate-300">
                        {{ t('Show on Options screen') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.show_in_market" type="checkbox" class="rounded border-slate-300">
                        {{ t('Show on Market screen') }}
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
                    <Link :href="route('admin.catalog.index')" class="rounded-xl px-4 py-2.5 text-sm text-slate-600">
                        {{ t('Cancel') }}
                    </Link>
                    <button
                        type="submit"
                        class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ isEdit ? t('Save type') : t('Create type') }}
                    </button>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
