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

const inputClass = 'mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-slate-400';

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
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-base font-semibold text-slate-950">{{ t('Texts') }}</h3>
            <AdminButton variant="secondary" size="sm" @click="syncTemplates">
                {{ t('Sync') }}
            </AdminButton>
        </div>

        <input
            v-model="search"
            type="search"
            :placeholder="t('Search…')"
            class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-slate-400"
        >

        <p v-if="filteredTemplates.length === 0" class="mt-6 text-center text-sm text-slate-500">
            {{ templates.length === 0 ? t('No texts yet. Press Sync.') : t('No results.') }}
        </p>

        <div v-else class="mt-4 grid gap-4 lg:grid-cols-[0.85fr_1.35fr]">
            <div class="max-h-[65vh] space-y-1 overflow-y-auto">
                <button
                    v-for="template in filteredTemplates"
                    :key="template.id"
                    type="button"
                    class="w-full rounded-lg px-3 py-2.5 text-left transition"
                    :class="selectedTemplateId === template.id ? 'bg-slate-950 text-white' : 'hover:bg-slate-50'"
                    @click="selectTemplate(template.id)"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-sm font-medium">{{ staffLabel(template) }}</span>
                        <AdminBadge
                            v-if="selectedTemplateId !== template.id"
                            :variant="template.is_active ? 'success' : 'neutral'"
                            :label="template.is_active ? 'On' : 'Off'"
                        />
                    </div>
                </button>
            </div>

            <form v-if="selectedTemplate && selectedDraft" class="space-y-4" @submit.prevent="saveSelectedTemplate">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="font-semibold text-slate-950">{{ staffLabel(selectedTemplate) }}</p>
                        <p class="mt-0.5 font-mono text-xs text-slate-400">{{ selectedTemplate.code }}</p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="selectedDraft.is_active" type="checkbox" class="rounded border-slate-300">
                        {{ t('Active') }}
                    </label>
                </div>

                <div class="flex gap-1 rounded-lg bg-slate-100 p-1">
                    <button
                        type="button"
                        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium"
                        :class="editLocale === 'en' ? 'bg-white shadow-sm' : ''"
                        @click="editLocale = 'en'"
                    >
                        English
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium"
                        :class="editLocale === 'ar' ? 'bg-white shadow-sm' : ''"
                        @click="editLocale = 'ar'"
                    >
                        العربية
                    </button>
                </div>

                <template v-if="editLocale === 'en'">
                    <label class="block">
                        <span class="text-sm text-slate-600">{{ t('Title') }}</span>
                        <input v-model="selectedDraft.subject" type="text" :class="inputClass">
                    </label>
                    <label class="block">
                        <span class="text-sm text-slate-600">{{ t('Message') }}</span>
                        <textarea v-model="selectedDraft.body" rows="4" :class="inputClass" required />
                    </label>
                </template>

                <template v-else>
                    <label class="block">
                        <span class="text-sm text-slate-600">{{ t('Title') }}</span>
                        <input v-model="selectedDraft.translations.ar.subject" type="text" dir="rtl" :class="inputClass">
                    </label>
                    <label class="block">
                        <span class="text-sm text-slate-600">{{ t('Message') }}</span>
                        <textarea v-model="selectedDraft.translations.ar.body" rows="4" dir="rtl" :class="inputClass" />
                    </label>
                </template>

                <div v-if="(selectedTemplate.variables ?? []).length">
                    <p class="text-xs text-slate-500">{{ t('Insert') }}</p>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <button
                            v-for="variable in selectedTemplate.variables"
                            :key="variable"
                            type="button"
                            class="rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700 hover:bg-slate-200"
                            @click="insertVariable(variable)"
                        >
                            {{ variableToken(variable) }}
                        </button>
                    </div>
                </div>

                <div>
                    <p class="text-sm text-slate-600">{{ t('Send via') }}</p>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <button
                            v-for="channel in availableChannels"
                            :key="channel"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium"
                            :class="selectedDraft.channels.includes(channel) ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600'"
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
    </section>
</template>
