<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import RichTextEditor from '../../components/RichTextEditor.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    tabs: { type: Array, default: () => [] },
});

const { t, isArabic } = useAdminLocale();
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const legalTabs = computed(() => props.tabs.filter((tab) => tab.group === 'legal'));
const productTabs = computed(() => props.tabs.filter((tab) => tab.group === 'product'));

const initialTab = () => {
    const requested = new URLSearchParams(window.location.search).get('tab') ?? '';
    if (props.tabs.some((tab) => tab.tab_id === requested)) {
        return requested;
    }

    return props.tabs[0]?.tab_id ?? 'privacy-policy';
};

const activeTab = ref(initialTab());

const activeTabMeta = computed(() => props.tabs.find((tab) => tab.tab_id === activeTab.value) ?? null);
const activePage = computed(() => activeTabMeta.value?.page ?? null);
const isProductPolicy = computed(() => activePage.value?.category === 'product_policy');

const tabLabel = (tab) => (isArabic.value ? (tab.label_ar || tab.label) : tab.label);

const form = useForm({
    slug: '',
    category: '',
    product: '',
    title_en: '',
    title_ar: '',
    body_en: '',
    body_ar: '',
    url: '',
    sort_order: 0,
    is_active: true,
});

const syncFormFromPage = (pageData) => {
    if (!pageData) {
        return;
    }

    form.defaults({
        slug: pageData.slug ?? '',
        category: pageData.category ?? 'legal',
        product: pageData.product ?? '',
        title_en: pageData.title_en ?? '',
        title_ar: pageData.title_ar ?? '',
        body_en: pageData.body_en ?? '',
        body_ar: pageData.body_ar ?? '',
        url: pageData.url ?? '',
        sort_order: pageData.sort_order ?? 0,
        is_active: Boolean(pageData.is_active),
    });
    form.reset();
    form.clearErrors();
};

watch(activePage, (pageData) => {
    syncFormFromPage(pageData);
}, { immediate: true });

onMounted(() => {
    const requested = new URLSearchParams(window.location.search).get('tab');
    if (requested && props.tabs.some((tab) => tab.tab_id === requested)) {
        activeTab.value = requested;
    }
});

const selectTab = (tabId) => {
    activeTab.value = tabId;

    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url);
};

const tabButtonClass = (tabId) => (
    activeTab.value === tabId
        ? 'bg-slate-950 text-white shadow-sm'
        : 'bg-slate-50 text-slate-600 hover:bg-slate-100'
);

const submit = () => {
    if (!activePage.value?.id) {
        return;
    }

    form.put(route('admin.content.update', activePage.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', activeTab.value);
            window.history.replaceState({}, '', url);
        },
    });
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Policies & Terms')" />

        <section class="mx-auto max-w-5xl space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-2xl font-semibold text-slate-950">{{ t('Policies & Terms') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {{ t('Edit all legal pages and product policies from one screen. The mobile app loads each section from the same API by product or slug.') }}
                </p>
            </div>

            <div
                v-if="flashSuccess"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ flashSuccess }}
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Legal') }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="tab in legalTabs"
                                :key="tab.tab_id"
                                type="button"
                                class="rounded-xl px-3 py-2 text-sm font-medium transition"
                                :class="tabButtonClass(tab.tab_id)"
                                @click="selectTab(tab.tab_id)"
                            >
                                {{ tabLabel(tab) }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Products') }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="tab in productTabs"
                                :key="tab.tab_id"
                                type="button"
                                class="rounded-xl px-3 py-2 text-sm font-medium transition"
                                :class="tabButtonClass(tab.tab_id)"
                                @click="selectTab(tab.tab_id)"
                            >
                                {{ tabLabel(tab) }}
                            </button>
                        </div>
                    </div>
                </div>

                <form v-if="activePage" class="mt-6 space-y-4 border-t border-slate-100 pt-6" @submit.prevent="submit">
                    <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        <p class="font-medium text-slate-800">{{ tabLabel(activeTabMeta) }}</p>
                        <p class="mt-1">
                            {{ isProductPolicy
                                ? t('Shown in the app on this product checkout screen. Fare rules from the provider stay separate.')
                                : t('Shown in app settings and checkout consent.') }}
                        </p>
                        <p class="mt-1 font-mono text-xs text-slate-500">
                            API: {{ isProductPolicy ? `/api/v1/pages/product/${activePage.product}` : `/api/v1/pages/${activePage.slug}` }}
                        </p>
                        <p v-if="activePage.web_url_ar" class="mt-2 break-all font-mono text-xs text-slate-500">
                            Web AR: {{ activePage.web_url_ar }}
                        </p>
                        <p v-if="activePage.web_url_en" class="mt-1 break-all font-mono text-xs text-slate-500">
                            Web EN: {{ activePage.web_url_en }}
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Slug') }}</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm disabled:bg-slate-50"
                                readonly
                            >
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
                                {{ t('Leave empty to use the public web page. Set an https:// link only to open a different website.') }}
                            </p>
                            <p v-if="form.errors.url" class="mt-1 text-sm text-rose-600">{{ form.errors.url }}</p>
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
                            <input v-model="form.title_ar" type="text" dir="rtl" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required>
                            <p v-if="form.errors.title_ar" class="mt-1 text-sm text-rose-600">{{ form.errors.title_ar }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Body (EN)') }}</label>
                        <RichTextEditor v-model="form.body_en" dir="ltr" />
                        <p v-if="form.errors.body_en" class="mt-1 text-sm text-rose-600">{{ form.errors.body_en }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Body (AR)') }}</label>
                        <RichTextEditor v-model="form.body_ar" dir="rtl" />
                        <p v-if="form.errors.body_ar" class="mt-1 text-sm text-rose-600">{{ form.errors.body_ar }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300">
                        {{ t('Active') }}
                    </label>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            {{ t('Save changes') }}
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </AdminLayout>
</template>
