<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminBadge from '../../components/AdminBadge.vue';
import AdminButton from '../../components/AdminButton.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    templateCategories: { type: Array, default: () => [] },
    availableChannels: { type: Array, default: () => ['in_app', 'email', 'push', 'sms', 'whatsapp'] },
});

const { t, isArabic } = useAdminLocale();

const search = ref('');
const selectedTemplateId = ref(null);
const editLocale = ref(isArabic.value ? 'ar' : 'en');
const drafts = reactive({});

const pretty = (value) => {
    if (!value) return t('Not available');
    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

const variableToken = (name) => `{${name}}`;

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
        if (!query) return true;

        const haystack = [
            template.code,
            template.name,
            template.label,
            template.label_ar,
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

const ensureSelection = () => {
    const exists = filteredTemplates.value.some((item) => item.id === selectedTemplateId.value);
    if (exists) return;
    selectedTemplateId.value = filteredTemplates.value[0]?.id ?? null;
};

const selectTemplate = (id) => {
    selectedTemplateId.value = id;
};

const toggleChannel = (channel) => {
    if (!selectedDraft.value) return;

    if (selectedDraft.value.channels.includes(channel)) {
        selectedDraft.value.channels = selectedDraft.value.channels.filter((item) => item !== channel);
        return;
    }

    selectedDraft.value.channels = [...selectedDraft.value.channels, channel];
};

const insertVariable = (variable) => {
    if (!selectedDraft.value) return;
    const token = variableToken(variable);

    if (editLocale.value === 'ar') {
        selectedDraft.value.translations.ar.body = `${selectedDraft.value.translations.ar.body ?? ''}${token}`;
        return;
    }

    selectedDraft.value.body = `${selectedDraft.value.body ?? ''}${token}`;
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

const fieldClass = 'mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none transition focus:border-[#2f6db8] focus:ring-4 focus:ring-[#2f6db8]/15';

watch(
    () => props.templates,
    () => {
        hydrateDrafts();
        ensureSelection();
    },
    { deep: true, immediate: true },
);

watch(search, ensureSelection);
</script>

<template>
    <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-[0_12px_40px_rgba(15,23,42,0.04)]">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <h3 class="text-lg font-semibold tracking-tight text-slate-950">{{ t('Templates') }}</h3>
            <AdminButton variant="secondary" size="sm" @click="syncTemplates">
                {{ t('Sync') }}
            </AdminButton>
        </div>

        <div class="p-5 sm:p-6">
            <input
                v-model="search"
                type="search"
                :placeholder="t('Search…')"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none transition focus:border-[#2f6db8] focus:ring-4 focus:ring-[#2f6db8]/15"
            >

            <p v-if="filteredTemplates.length === 0" class="mt-10 text-center text-sm text-slate-500">
                {{ templates.length === 0 ? t('No texts yet. Press Sync.') : t('No results.') }}
            </p>

            <div v-else class="mt-5 grid gap-5 lg:grid-cols-[0.9fr_1.3fr]">
                <div class="max-h-[68vh] space-y-1.5 overflow-y-auto rounded-[1.35rem] bg-slate-50 p-2">
                    <button
                        v-for="template in filteredTemplates"
                        :key="template.id"
                        type="button"
                        class="w-full rounded-2xl px-3.5 py-3 text-start transition"
                        :class="selectedTemplateId === template.id
                            ? 'bg-[#2f6db8] text-white shadow-lg shadow-[#2f6db8]/25'
                            : 'bg-white text-slate-800 hover:bg-white/80'"
                        @click="selectTemplate(template.id)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-semibold">{{ staffLabel(template) }}</span>
                            <AdminBadge
                                v-if="selectedTemplateId !== template.id"
                                :variant="template.is_active ? 'success' : 'neutral'"
                                :label="template.is_active ? t('On') : t('Off')"
                            />
                        </div>
                    </button>
                </div>

                <form v-if="selectedTemplate && selectedDraft" class="space-y-5" @submit.prevent="saveSelectedTemplate">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-lg font-semibold text-slate-950">{{ staffLabel(selectedTemplate) }}</p>
                            <p class="mt-1 font-mono text-xs text-slate-400">{{ selectedTemplate.code }}</p>
                        </div>
                        <label class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-sm text-slate-700">
                            <input v-model="selectedDraft.is_active" type="checkbox" class="rounded border-slate-300 text-[#2f6db8] focus:ring-[#2f6db8]">
                            {{ t('Active') }}
                        </label>
                    </div>

                    <div class="inline-flex rounded-2xl bg-slate-100 p-1">
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                            :class="editLocale === 'en' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'"
                            @click="editLocale = 'en'"
                        >
                            English
                        </button>
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                            :class="editLocale === 'ar' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'"
                            @click="editLocale = 'ar'"
                        >
                            العربية
                        </button>
                    </div>

                    <template v-if="editLocale === 'en'">
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">{{ t('Title') }}</span>
                            <input v-model="selectedDraft.subject" type="text" :class="fieldClass">
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">{{ t('Message') }}</span>
                            <textarea v-model="selectedDraft.body" rows="5" :class="fieldClass" required />
                        </label>
                    </template>

                    <template v-else>
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">{{ t('Title') }}</span>
                            <input v-model="selectedDraft.translations.ar.subject" type="text" dir="rtl" :class="fieldClass">
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">{{ t('Message') }}</span>
                            <textarea v-model="selectedDraft.translations.ar.body" rows="5" dir="rtl" :class="fieldClass" />
                        </label>
                    </template>

                    <div v-if="(selectedTemplate.variables ?? []).length">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{{ t('Insert') }}</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="variable in selectedTemplate.variables"
                                :key="variable"
                                type="button"
                                class="rounded-full bg-slate-100 px-3 py-1.5 font-mono text-xs text-slate-700 transition hover:bg-[#2f6db8]/10 hover:text-[#255991]"
                                @click="insertVariable(variable)"
                            >
                                {{ variableToken(variable) }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-slate-700">{{ t('Send via') }}</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="channel in availableChannels"
                                :key="channel"
                                type="button"
                                class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition"
                                :class="selectedDraft.channels.includes(channel)
                                    ? 'bg-[#2f6db8] text-white'
                                    : 'bg-slate-100 text-slate-600'"
                                @click="toggleChannel(channel)"
                            >
                                {{ pretty(channel) }}
                            </button>
                        </div>
                    </div>

                    <AdminButton
                        type="submit"
                        :processing="selectedDraft.saving"
                        :disabled="(selectedDraft.channels?.length ?? 0) === 0"
                    >
                        {{ t('Save') }}
                    </AdminButton>
                </form>
            </div>
        </div>
    </section>
</template>
