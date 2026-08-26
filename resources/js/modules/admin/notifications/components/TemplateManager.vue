<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    templateCategories: { type: Array, default: () => [] },
    availableChannels: { type: Array, default: () => ['in_app', 'email', 'push', 'sms', 'whatsapp'] },
});

const { t, isArabic } = useAdminLocale();

const search = ref('');
const categoryFilter = ref('all');
const statusFilter = ref('all');
const channelFilter = ref('all');
const selectedTemplateId = ref(null);
const activeEditorTab = ref('content');
const previewLocale = ref('en');
const drafts = reactive({});

const pretty = (value) => {
    if (!value) return t('Not available');
    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

const renderTemplate = (content, variables = {}) => {
    if (!content) return '';
    return String(content).replace(/\{([a-zA-Z0-9_]+)\}/g, (_, key) => variables[key] ?? `{${key}}`);
};

const variableToken = (name) => `{${name}}`;

const categoryLabel = (templateOrValue) => {
    if (templateOrValue && typeof templateOrValue === 'object') {
        return isArabic.value
            ? (templateOrValue.category_label_ar || templateOrValue.category_label || templateOrValue.category)
            : (templateOrValue.category_label || templateOrValue.category);
    }

    const match = props.templateCategories.find((item) => item.value === templateOrValue);

    if (!match) return templateOrValue;

    return isArabic.value ? (match.label_ar || match.label) : match.label;
};

const staffLabel = (template) => {
    if (!template) return '';

    return isArabic.value
        ? (template.label_ar || template.name || template.code)
        : (template.label || template.name || template.code);
};

const hydrateDrafts = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);

    for (const template of props.templates ?? []) {
        drafts[template.id] = {
            name: template.name ?? '',
            category: template.category ?? 'general',
            description: template.description ?? '',
            subject: template.subject ?? '',
            body: template.body ?? '',
            translations: {
                ar: {
                    subject: template.translations?.ar?.subject ?? '',
                    body: template.translations?.ar?.body ?? '',
                },
            },
            channels: [...(template.channels ?? [])],
            variables: [...(template.variables ?? [])],
            is_active: Boolean(template.is_active),
            saving: false,
        };
    }
};

const filteredTemplates = computed(() => {
    const query = search.value.trim().toLowerCase();

    return (props.templates ?? []).filter((template) => {
        if (categoryFilter.value !== 'all' && template.category !== categoryFilter.value) return false;
        if (statusFilter.value === 'active' && !template.is_active) return false;
        if (statusFilter.value === 'inactive' && template.is_active) return false;
        if (channelFilter.value !== 'all' && !(template.channels ?? []).includes(channelFilter.value)) return false;
        if (!query) return true;

        const haystack = [
            template.code,
            template.name,
            template.label,
            template.label_ar,
            template.description,
            template.category_label,
            template.category_label_ar,
            template.subject,
            template.body,
            template.translations?.ar?.subject,
            template.translations?.ar?.body,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

const selectedTemplate = computed(() => filteredTemplates.value.find((item) => item.id === selectedTemplateId.value) ?? null);
const selectedDraft = computed(() => (selectedTemplate.value ? drafts[selectedTemplate.value.id] : null));

const stats = computed(() => ({
    total: props.templates?.length ?? 0,
    active: (props.templates ?? []).filter((item) => item.is_active).length,
    arabic: (props.templates ?? []).filter((item) => item.has_arabic).length,
    filtered: filteredTemplates.value.length,
}));

const previewFor = computed(() => {
    if (!selectedTemplate.value || !selectedDraft.value) return { title: '', body: '', rtl: false };

    const variables = selectedTemplate.value.sample_variables ?? {};
    const isArabic = previewLocale.value === 'ar';
    const title = isArabic
        ? (selectedDraft.value.translations.ar.subject || selectedDraft.value.subject)
        : selectedDraft.value.subject;
    const body = isArabic
        ? (selectedDraft.value.translations.ar.body || selectedDraft.value.body)
        : selectedDraft.value.body;

    return {
        title: renderTemplate(title, variables),
        body: renderTemplate(body, variables),
        rtl: isArabic,
    };
});

const ensureSelection = () => {
    const exists = filteredTemplates.value.some((item) => item.id === selectedTemplateId.value);
    if (exists) return;
    selectedTemplateId.value = filteredTemplates.value[0]?.id ?? null;
};

const selectTemplate = (id) => {
    selectedTemplateId.value = id;
    activeEditorTab.value = 'content';
};

const toggleChannel = (channel) => {
    if (!selectedDraft.value) return;

    if (selectedDraft.value.channels.includes(channel)) {
        selectedDraft.value.channels = selectedDraft.value.channels.filter((item) => item !== channel);
        return;
    }

    selectedDraft.value.channels = [...selectedDraft.value.channels, channel];
};

const insertVariable = (target, variable, locale = 'en') => {
    if (!selectedDraft.value) return;
    const token = variableToken(variable);

    if (locale === 'ar') {
        selectedDraft.value.translations.ar[target] = `${selectedDraft.value.translations.ar[target] ?? ''}${token}`;
        return;
    }

    selectedDraft.value[target] = `${selectedDraft.value[target] ?? ''}${token}`;
};

const syncTemplates = () => {
    router.post(route('admin.notifications.templates.sync'), {}, { preserveScroll: true });
};

const saveSelectedTemplate = () => {
    if (!selectedTemplate.value || !selectedDraft.value) return;

    selectedDraft.value.saving = true;

    useForm({
        name: selectedDraft.value.name,
        category: selectedDraft.value.category,
        description: selectedDraft.value.description,
        subject: selectedDraft.value.subject,
        body: selectedDraft.value.body,
        translations: selectedDraft.value.translations,
        channels: selectedDraft.value.channels,
        variables: selectedDraft.value.variables,
        is_active: selectedDraft.value.is_active,
    }).put(route('admin.notifications.templates.update', selectedTemplate.value.id), {
        preserveScroll: true,
        onFinish: () => {
            if (selectedDraft.value) selectedDraft.value.saving = false;
        },
    });
};

watch(
    () => props.templates,
    () => {
        hydrateDrafts();
        ensureSelection();
    },
    { deep: true, immediate: true },
);

watch([search, categoryFilter, statusFilter, channelFilter], ensureSelection);
</script>

<template>
    <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">{{ t('Template manager') }}</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">
                    {{ t('Search, preview, and edit bilingual notification templates used by mobile push, inbox, and email.') }}
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-800 transition hover:bg-slate-50"
                @click="syncTemplates"
            >
                {{ t('Sync templates') }}
            </button>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Total templates') }}</p>
                <p class="mt-1 text-2xl font-semibold text-slate-950">{{ stats.total }}</p>
            </article>
            <article class="rounded-2xl bg-emerald-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">{{ t('Active') }}</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ stats.active }}</p>
            </article>
            <article class="rounded-2xl bg-sky-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">{{ t('With Arabic') }}</p>
                <p class="mt-1 text-2xl font-semibold text-sky-900">{{ stats.arabic }}</p>
            </article>
            <article class="rounded-2xl bg-violet-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-violet-700">{{ t('Showing') }}</p>
                <p class="mt-1 text-2xl font-semibold text-violet-900">{{ stats.filtered }}</p>
            </article>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-[1.4fr_repeat(3,minmax(0,1fr))]">
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Search templates') }}</span>
                <input
                    v-model="search"
                    type="search"
                    :placeholder="t('Code, name, Arabic or English text...')"
                    class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none transition focus:border-slate-400"
                >
            </label>

            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Category') }}</span>
                <select v-model="categoryFilter" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                    <option value="all">{{ t('All categories') }}</option>
                    <option v-for="category in templateCategories" :key="category.value" :value="category.value">
                        {{ isArabic ? (category.label_ar || category.label) : t(category.label) }}
                    </option>
                </select>
            </label>

            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Status') }}</span>
                <select v-model="statusFilter" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                    <option value="all">{{ t('All statuses') }}</option>
                    <option value="active">{{ t('Active only') }}</option>
                    <option value="inactive">{{ t('Inactive only') }}</option>
                </select>
            </label>

            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Channel') }}</span>
                <select v-model="channelFilter" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                    <option value="all">{{ t('All channels') }}</option>
                    <option v-for="channel in availableChannels" :key="channel" :value="channel">
                        {{ pretty(channel) }}
                    </option>
                </select>
            </label>
        </div>

        <div v-if="filteredTemplates.length === 0" class="mt-4 rounded-2xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-600">
            {{ templates.length === 0 ? t('No templates yet. Click Sync templates to load the engine catalog.') : t('No templates match your filters.') }}
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-[0.9fr_1.4fr]">
            <aside class="rounded-3xl border border-slate-200 bg-slate-50 p-3">
                <div class="mb-2 flex items-center justify-between px-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Templates') }}</p>
                    <p class="text-xs text-slate-500">{{ filteredTemplates.length }}</p>
                </div>

                <div class="max-h-[70vh] space-y-2 overflow-y-auto pr-1">
                    <button
                        v-for="template in filteredTemplates"
                        :key="template.id"
                        type="button"
                        class="w-full rounded-2xl border px-3 py-3 text-left transition"
                        :class="selectedTemplateId === template.id ? 'border-slate-900 bg-white shadow-sm' : 'border-transparent bg-white/70 hover:border-slate-200'"
                        @click="selectTemplate(template.id)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ staffLabel(template) }}</p>
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
                                :class="template.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                            >
                                {{ template.is_active ? t('Active') : t('Inactive') }}
                            </span>
                        </div>
                        <p class="mt-1 truncate text-xs text-slate-500">{{ categoryLabel(template) }}</p>
                        <p class="mt-0.5 truncate font-mono text-[11px] text-slate-400">{{ template.code }}</p>
                    </button>
                </div>
            </aside>

            <section class="rounded-3xl border border-slate-200 bg-white p-4">
                <div v-if="!selectedTemplate || !selectedDraft" class="rounded-2xl bg-slate-50 p-8 text-center text-sm text-slate-500">
                    {{ t('No templates match your filters.') }}
                </div>

                <form v-else @submit.prevent="saveSelectedTemplate">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-4">
                        <div>
                            <p class="text-base font-semibold text-slate-950">{{ staffLabel(selectedTemplate) }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ categoryLabel(selectedTemplate) }}</p>
                            <p class="mt-1 font-mono text-xs text-slate-400">{{ selectedTemplate.code }} · v{{ selectedTemplate.version }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="selectedDraft.is_active" type="checkbox" class="rounded border-slate-300">
                                {{ t('Template is active') }}
                            </label>
                            <button
                                type="submit"
                                class="inline-flex rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
                                :disabled="selectedDraft.saving || (selectedDraft.channels?.length ?? 0) === 0"
                            >
                                {{ selectedDraft.saving ? t('Saving...') : t('Save changes') }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="rounded-2xl px-3 py-2 text-sm font-medium" :class="activeEditorTab === 'content' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700'" @click="activeEditorTab = 'content'">
                            {{ t('Content') }}
                        </button>
                        <button type="button" class="rounded-2xl px-3 py-2 text-sm font-medium" :class="activeEditorTab === 'channels' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700'" @click="activeEditorTab = 'channels'">
                            {{ t('Channels') }}
                        </button>
                        <button type="button" class="rounded-2xl px-3 py-2 text-sm font-medium" :class="activeEditorTab === 'preview' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700'" @click="activeEditorTab = 'preview'">
                            {{ t('Preview') }}
                        </button>
                    </div>

                    <div v-if="activeEditorTab === 'content'" class="mt-4 space-y-4">
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Display name') }}</span>
                                <input v-model="selectedDraft.name" type="text" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required>
                                <p class="mt-1 text-xs text-slate-500">{{ selectedTemplate.label_ar }}</p>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Category') }}</span>
                                <select v-model="selectedDraft.category" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                    <option v-for="category in templateCategories" :key="category.value" :value="category.value">{{ isArabic ? (category.label_ar || category.label) : t(category.label) }}</option>
                                </select>
                            </label>
                        </div>

                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('When is this sent?') }}</span>
                            <textarea v-model="selectedDraft.description" rows="2" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm leading-6" :placeholder="t('Short explanation for admins')" />
                        </label>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" class="rounded-full px-3 py-1.5 text-xs font-semibold" :class="previewLocale === 'en' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600'" @click="previewLocale = 'en'">English</button>
                                <button type="button" class="rounded-full px-3 py-1.5 text-xs font-semibold" :class="previewLocale === 'ar' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600'" @click="previewLocale = 'ar'">العربية</button>
                            </div>

                            <div v-if="previewLocale === 'en'" class="mt-4 space-y-3">
                                <label class="block">
                                    <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Subject (English)') }}</span>
                                    <input v-model="selectedDraft.subject" type="text" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Body (English)') }}</span>
                                    <textarea v-model="selectedDraft.body" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm leading-6" required />
                                </label>
                            </div>

                            <div v-else class="mt-4 space-y-3">
                                <label class="block">
                                    <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Subject (Arabic)') }}</span>
                                    <input v-model="selectedDraft.translations.ar.subject" type="text" dir="rtl" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Body (Arabic)') }}</span>
                                    <textarea v-model="selectedDraft.translations.ar.body" rows="4" dir="rtl" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm leading-6" />
                                </label>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeEditorTab === 'channels'" class="mt-4 space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Channels') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="channel in availableChannels"
                                    :key="channel"
                                    type="button"
                                    class="rounded-full px-3 py-1.5 text-xs font-medium"
                                    :class="selectedDraft.channels.includes(channel) ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600'"
                                    @click="toggleChannel(channel)"
                                >
                                    {{ pretty(channel) }}
                                </button>
                            </div>
                        </div>

                        <div v-if="(selectedTemplate.variables ?? []).length">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Variables — click to insert') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="variable in selectedTemplate.variables"
                                    :key="variable"
                                    type="button"
                                    class="rounded-full bg-violet-50 px-3 py-1.5 font-mono text-xs text-violet-700"
                                    @click="insertVariable(previewLocale === 'ar' ? 'body' : 'body', variable, previewLocale)"
                                >
                                    {{ variableToken(variable) }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="mt-4 grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                        <aside class="rounded-2xl border border-slate-200 bg-slate-950 p-4 text-white">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ t('Mobile preview') }}</p>
                            <div class="mt-4 rounded-3xl bg-white/10 p-4 backdrop-blur">
                                <div class="flex items-center gap-2 text-xs text-slate-300">
                                    <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400" />
                                    Booke · {{ pretty(selectedDraft.channels?.[0] ?? 'push') }}
                                </div>
                                <p class="mt-3 text-sm font-semibold" :dir="previewFor.rtl ? 'rtl' : 'ltr'">
                                    {{ previewFor.title || t('Notification title') }}
                                </p>
                                <p class="mt-2 text-sm leading-6 text-slate-200" :dir="previewFor.rtl ? 'rtl' : 'ltr'">
                                    {{ previewFor.body || t('Notification body preview will appear here.') }}
                                </p>
                            </div>
                        </aside>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Sample data') }}</p>
                            <dl class="mt-2 space-y-1">
                                <div v-for="(value, key) in selectedTemplate.sample_variables" :key="key" class="grid grid-cols-[1fr_1.2fr] gap-2">
                                    <dt class="font-mono text-slate-500">{{ key }}</dt>
                                    <dd class="truncate text-slate-800">{{ value }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </section>
</template>
