<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    pages: { type: Array, default: () => [] },
});

const { t, isArabic } = useAdminLocale();
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const categoryLabel = (item) => (isArabic.value ? (item.category_label_ar || item.category_label) : item.category_label);
const productLabel = (item) => (isArabic.value ? (item.product_label_ar || item.product_label) : item.product_label);

const destroyPage = (item) => {
    if (!window.confirm(t('Delete this page?'))) {
        return;
    }

    router.delete(route('admin.content.destroy', item.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Content Pages')" />

        <section class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-950">{{ t('Content Pages') }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                        {{ t('Manage legal pages and product policies shown in the mobile app. Product policies appear next to fare rules at checkout; fare rules still come from the provider.') }}
                    </p>
                </div>
                <Link
                    :href="route('admin.content.create')"
                    class="inline-flex rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white"
                >
                    {{ t('Add page') }}
                </Link>
            </div>

            <div
                v-if="flashSuccess"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ flashSuccess }}
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('Title (EN)') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('Slug') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('Category') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('Product') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('Sort') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('Status') }}</th>
                            <th class="px-4 py-3 font-medium" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in pages" :key="item.id" class="text-slate-800">
                            <td class="px-4 py-3 font-medium">{{ item.title_en }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ item.slug }}</td>
                            <td class="px-4 py-3">{{ categoryLabel(item) }}</td>
                            <td class="px-4 py-3">{{ productLabel(item) || '—' }}</td>
                            <td class="px-4 py-3">{{ item.sort_order }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="item.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ item.is_active ? t('Active') : t('Inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-end">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="route('admin.content.edit', item.id)"
                                        class="rounded-lg px-3 py-1.5 text-sm text-cyan-700 hover:bg-cyan-50"
                                    >
                                        {{ t('Edit') }}
                                    </Link>
                                    <button
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-sm text-rose-700 hover:bg-rose-50"
                                        @click="destroyPage(item)"
                                    >
                                        {{ t('Delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="pages.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                                {{ t('No content pages yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AdminLayout>
</template>
