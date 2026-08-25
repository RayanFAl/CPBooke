<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminConfirm } from '../../composables/useAdminConfirm';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    types: { type: Array, default: () => [] },
});

const { t } = useAdminLocale();
const { confirm } = useAdminConfirm();
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const destroyType = async (type) => {
    if (!await confirm({
        title: 'Confirm action',
        message: t('Delete this catalog type?'),
        confirmLabel: 'Delete',
        variant: 'danger',
    })) {
        return;
    }

    router.delete(route('admin.catalog.destroy', type.id));
};

const prettyAction = (type) => {
    const label = t(`home.action.${type.action_type}`);

    if (type.action_type === 'none') {
        return label;
    }

    if (type.action_value) {
        return `${label}: ${type.action_value}`;
    }

    return label;
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Options & Market')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-slate-950">{{ t('Options & Market') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ t('Manage the product types shown on the mobile Options and Market screens. Upload a separate image for each screen, or add a new type anytime.') }}
                        </p>
                    </div>
                    <Link
                        :href="route('admin.catalog.create')"
                        class="inline-flex shrink-0 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white"
                    >
                        {{ t('Add type') }}
                    </Link>
                </div>
            </div>

            <div
                v-if="flashSuccess"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ t(flashSuccess) }}
            </div>

            <div v-if="types.length === 0" class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                <p class="text-sm text-slate-600">{{ t('No catalog types yet. Add travel insurance, eSIM, or any custom type.') }}</p>
                <Link
                    :href="route('admin.catalog.create')"
                    class="mt-4 inline-flex rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white"
                >
                    {{ t('Add type') }}
                </Link>
            </div>

            <div v-else class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="type in types"
                    :key="type.id"
                    class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="grid grid-cols-2 gap-px bg-slate-100">
                        <div class="bg-white">
                            <p class="px-3 pt-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                                {{ t('Options image') }}
                            </p>
                            <div class="p-3">
                                <img
                                    v-if="type.options_image_url"
                                    :src="type.options_image_url"
                                    :alt="type.title_en"
                                    class="h-32 w-full rounded-2xl object-cover"
                                >
                                <div v-else class="flex h-32 items-center justify-center rounded-2xl bg-slate-50 text-xs text-slate-400">
                                    {{ t('No image') }}
                                </div>
                            </div>
                        </div>
                        <div class="bg-white">
                            <p class="px-3 pt-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                                {{ t('Market image') }}
                            </p>
                            <div class="p-3">
                                <img
                                    v-if="type.market_image_url"
                                    :src="type.market_image_url"
                                    :alt="type.title_en"
                                    class="h-32 w-full rounded-2xl object-cover"
                                >
                                <div v-else class="flex h-32 items-center justify-center rounded-2xl bg-slate-50 text-xs text-slate-400">
                                    {{ t('No image') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 border-t border-slate-100 px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">{{ type.title_en }}</h3>
                                <p v-if="type.title_ar" class="mt-0.5 text-sm text-slate-500" dir="rtl">{{ type.title_ar }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ type.key }}</p>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="type.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                            >
                                {{ type.is_active ? t('Active') : t('Inactive') }}
                            </span>
                        </div>

                        <p class="text-xs text-slate-500">{{ prettyAction(type) }}</p>

                        <div class="flex flex-wrap gap-2 text-xs text-slate-500">
                            <span
                                class="rounded-lg px-2 py-1"
                                :class="type.show_in_options ? 'bg-cyan-50 text-cyan-800' : 'bg-slate-50 text-slate-400'"
                            >
                                {{ t('Options') }}
                            </span>
                            <span
                                class="rounded-lg px-2 py-1"
                                :class="type.show_in_market ? 'bg-cyan-50 text-cyan-800' : 'bg-slate-50 text-slate-400'"
                            >
                                {{ t('Market') }}
                            </span>
                        </div>

                        <div class="flex gap-3 pt-1">
                            <Link
                                :href="route('admin.catalog.edit', type.id)"
                                class="text-sm font-medium text-cyan-700 hover:underline"
                            >
                                {{ t('Edit') }}
                            </Link>
                            <button
                                type="button"
                                class="text-sm font-medium text-rose-600 hover:underline"
                                @click="destroyType(type)"
                            >
                                {{ t('Delete') }}
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </AdminLayout>
</template>
