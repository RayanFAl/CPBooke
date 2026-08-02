<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    tab: { type: String, default: 'banners' },
    banners: { type: Array, default: () => [] },
    offers: { type: Array, default: () => [] },
});

const { t } = useAdminLocale();
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const switchTab = (tab) => {
    router.get(route('admin.home.index'), { tab }, { preserveState: true, replace: true });
};

const destroyBanner = (banner) => {
    if (!window.confirm(t('Delete this banner?'))) {
        return;
    }

    router.delete(route('admin.home.banners.destroy', banner.id));
};

const destroyOffer = (offer) => {
    if (!window.confirm(t('Delete this offer?'))) {
        return;
    }

    router.delete(route('admin.home.offers.destroy', offer.id));
};

const prettyAction = (item) => {
    if (item.action_type === 'none') {
        return t('Display only');
    }

    if (item.action_value) {
        return `${item.action_type}: ${item.action_value}`;
    }

    return item.action_type;
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Home Content')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-2xl font-semibold text-slate-950">{{ t('Home Content') }}</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                    {{ t('Manage mobile home hero banners and special offer cards.') }}
                </p>
            </div>

            <div
                v-if="flashSuccess"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ flashSuccess }}
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="rounded-full px-4 py-2 text-sm font-medium"
                    :class="tab === 'banners' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700'"
                    @click="switchTab('banners')"
                >
                    {{ t('Banners') }} ({{ banners.length }})
                </button>
                <button
                    type="button"
                    class="rounded-full px-4 py-2 text-sm font-medium"
                    :class="tab === 'offers' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700'"
                    @click="switchTab('offers')"
                >
                    {{ t('Offers') }} ({{ offers.length }})
                </button>

                <div class="ms-auto">
                    <Link
                        v-if="tab === 'banners'"
                        :href="route('admin.home.banners.create')"
                        class="inline-flex rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white"
                    >
                        {{ t('Add banner') }}
                    </Link>
                    <Link
                        v-else
                        :href="route('admin.home.offers.create')"
                        class="inline-flex rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white"
                    >
                        {{ t('Add offer') }}
                    </Link>
                </div>
            </div>

            <div v-if="tab === 'banners'" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('Preview') }}</th>
                            <th class="px-4 py-3">{{ t('Title') }}</th>
                            <th class="px-4 py-3">{{ t('Action') }}</th>
                            <th class="px-4 py-3">{{ t('Order') }}</th>
                            <th class="px-4 py-3">{{ t('Status') }}</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="banner in banners" :key="banner.id">
                            <td class="px-4 py-3">
                                <img
                                    v-if="banner.image_url"
                                    :src="banner.image_url"
                                    alt=""
                                    class="h-12 w-20 rounded-lg object-cover"
                                >
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-950">{{ banner.title_en }}</p>
                                <p class="text-xs text-slate-500">{{ banner.public_id }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ prettyAction(banner) }}</td>
                            <td class="px-4 py-3">{{ banner.sort_order }}</td>
                            <td class="px-4 py-3">
                                <span :class="banner.is_active ? 'text-emerald-700' : 'text-slate-400'">
                                    {{ banner.is_active ? t('Active') : t('Inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('admin.home.banners.edit', banner.id)" class="text-cyan-700 hover:underline">
                                    {{ t('Edit') }}
                                </Link>
                                <button type="button" class="ms-3 text-rose-600 hover:underline" @click="destroyBanner(banner)">
                                    {{ t('Delete') }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="banners.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                                {{ t('No banners yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('Preview') }}</th>
                            <th class="px-4 py-3">{{ t('Title') }}</th>
                            <th class="px-4 py-3">{{ t('Category') }}</th>
                            <th class="px-4 py-3">{{ t('Action') }}</th>
                            <th class="px-4 py-3">{{ t('Order') }}</th>
                            <th class="px-4 py-3">{{ t('Status') }}</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="offer in offers" :key="offer.id">
                            <td class="px-4 py-3">
                                <img
                                    v-if="offer.image_url"
                                    :src="offer.image_url"
                                    alt=""
                                    class="h-12 w-20 rounded-lg object-cover"
                                >
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-950">{{ offer.title_en }}</p>
                                <p class="text-xs text-slate-500">{{ offer.badge_en || offer.public_id }}</p>
                            </td>
                            <td class="px-4 py-3">{{ offer.category }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ prettyAction(offer) }}</td>
                            <td class="px-4 py-3">{{ offer.sort_order }}</td>
                            <td class="px-4 py-3">
                                <span :class="offer.is_active ? 'text-emerald-700' : 'text-slate-400'">
                                    {{ offer.is_active ? t('Active') : t('Inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('admin.home.offers.edit', offer.id)" class="text-cyan-700 hover:underline">
                                    {{ t('Edit') }}
                                </Link>
                                <button type="button" class="ms-3 text-rose-600 hover:underline" @click="destroyOffer(offer)">
                                    {{ t('Delete') }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="offers.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                                {{ t('No offers yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AdminLayout>
</template>
