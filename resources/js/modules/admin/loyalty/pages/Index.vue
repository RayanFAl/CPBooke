<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    program: {
        type: Object,
        required: true,
    },
});

const { t, forwardArrow } = useAdminLocale();
const page = usePage();
const activeTab = ref('overview');

const permissions = computed(() => page.props.auth.user?.permissions ?? []);
const canManageTiers = computed(() => permissions.value.includes('loyalty.manage'));
const canManageRules = computed(() => permissions.value.includes('loyalty.manage-rules'));
const canManageBenefits = computed(() => permissions.value.includes('loyalty.manage-benefits'));
const canEditProgram = computed(() => canManageTiers.value || canManageRules.value || canManageBenefits.value);

const workspaceTabs = computed(() => [
    { id: 'overview', label: t('Overview') },
    { id: 'program', label: t('Program') },
]);

const workspaceTabClass = (tabId) => (activeTab.value === tabId
    ? 'bg-slate-950 text-white'
    : 'text-slate-600 hover:bg-slate-100');

const pretty = (value) => {
    if (value === null || value === undefined || value === '') {
        return t('Not available');
    }

    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

const primaryDiscountBenefit = (tierId) => props.dashboard.benefits.find(
    (benefit) => benefit.tier_id === tierId
        && benefit.benefit_type === 'discount'
        && benefit.is_active,
);

const primaryRule = (tierId) => props.dashboard.rules.find((rule) => rule.tier_id === tierId);

const launchTiers = computed(() => props.dashboard.tiers
    .filter((tier) => tier.is_active && tier.level > 0)
    .map((tier) => {
        const rule = primaryRule(tier.id);
        const benefit = primaryDiscountBenefit(tier.id);

        return {
            tier,
            rule,
            benefit,
            users_count: tier.users_count,
            monthly_spend: rule?.min_period_spend ?? 0,
            duration_months: rule?.metadata?.benefit_duration_months ?? '',
            discount: benefit?.value ?? '',
        };
    }));

const requirementLabel = (monthlySpend) => {
    if (Number(monthlySpend) <= 0) {
        return t('Not configured');
    }

    return `${monthlySpend} ${props.program.default_currency} ${t('this month')}`;
};

const durationLabel = (months) => {
    if (!months) {
        return t('Not configured');
    }

    return `${months} ${t('months')}`;
};

const formatDiscount = (benefit) => {
    if (!benefit || benefit.value === '' || benefit.value === null) {
        return t('Not configured');
    }

    if (benefit.value_type === 'percentage') {
        return `${benefit.value}%`;
    }

    return `${benefit.value} ${props.program.default_currency}`;
};

const saveProgramTier = (entry) => {
    if (canManageTiers.value) {
        useForm({
            code: entry.tier.code,
            name: entry.tier.name,
            description: entry.tier.description ?? '',
            badge_label: entry.tier.badge_label ?? '',
            color_token: entry.tier.color_token ?? '',
            sort_order: entry.tier.sort_order,
            is_active: entry.tier.is_active,
            is_default: entry.tier.is_default,
        }).put(route('admin.loyalty.tiers.update', entry.tier.id), {
            preserveScroll: true,
        });
    }

    if (canManageRules.value && entry.rule) {
        useForm({
            name: entry.rule.name,
            rule_type: 'upgrade',
            min_completed_orders: entry.rule.min_completed_orders,
            min_lifetime_spend: entry.rule.min_lifetime_spend,
            min_period_orders: entry.rule.min_period_orders,
            min_period_spend: entry.rule.min_period_spend,
            period_days: entry.rule.period_days,
            allow_downgrade: entry.rule.allow_downgrade,
            is_active: entry.rule.is_active,
            priority: entry.rule.priority,
        }).put(route('admin.loyalty.rules.update', entry.rule.id), {
            preserveScroll: true,
        });
    }

    if (canManageBenefits.value && entry.benefit) {
        useForm({
            name: entry.benefit.name,
            description: entry.benefit.description ?? '',
            benefit_type: entry.benefit.benefit_type,
            value_type: entry.benefit.value_type,
            value: entry.benefit.value === '' ? null : entry.benefit.value,
            display_order: entry.benefit.display_order,
            is_highlighted: entry.benefit.is_highlighted,
            is_active: entry.benefit.is_active,
        }).put(route('admin.loyalty.benefits.update', entry.benefit.id), {
            preserveScroll: true,
        });
    }
};

const metrics = computed(() => [
    {
        key: 'profiles',
        label: t('Enrolled customers'),
        helper: t('Customers with a loyalty profile'),
        value: props.dashboard.metrics.profiles,
    },
    {
        key: 'upgrades_last_30_days',
        label: t('Upgrades in 30 days'),
        helper: t('Tier upgrades in the last month'),
        value: props.dashboard.metrics.upgrades_last_30_days,
        tone: 'text-cyan-700',
    },
    {
        key: 'average_completed_orders',
        label: t('Average completed orders'),
        helper: t('Completed bookings per profile'),
        value: props.dashboard.metrics.average_completed_orders,
    },
]);

const activeTiersCount = computed(() => launchTiers.value.length);

const programStatusLabel = computed(() => (props.program.loyalty_enabled ? t('Enabled') : t('Disabled')));

const programStatusTone = computed(() => {
    if (!props.program.loyalty_enabled) {
        return 'bg-rose-50 text-rose-700 ring-rose-200';
    }

    return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
});

const recentUsers = computed(() => props.dashboard.users_per_tier.flatMap((bucket) => bucket.users.map((entry) => ({
    ...entry,
    tier_name: bucket.tier.name,
    tier_level: bucket.tier.level,
}))).slice(0, 12));

const inputClass = 'mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-400';
</script>

<template>
    <Head :title="t('Loyalty')" />

    <AdminLayout
        title="Loyalty"
        description="Member discounts and automatic tier upgrades based on completed bookings."
    >
        <section class="space-y-4">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-4 py-5 sm:px-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                                {{ t('Retention') }}
                            </p>
                            <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('Loyalty program') }}</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                                {{ t('Customers unlock discounts by monthly spend. Each level stays active for a fixed number of months and renews when the spend target is hit again.') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium ring-1"
                                :class="programStatusTone"
                            >
                                {{ programStatusLabel }}
                            </span>
                            <span class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white">
                                {{ activeTiersCount }} {{ t('active tiers') }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <article
                            v-for="metric in metrics"
                            :key="metric.key"
                            class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3"
                        >
                            <p class="text-xs font-medium text-slate-500">{{ metric.label }}</p>
                            <p class="mt-1 text-xl font-semibold text-slate-950" :class="metric.tone">{{ metric.value }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ metric.helper }}</p>
                        </article>
                    </div>
                </div>

                <div class="border-b border-slate-100 px-3 py-2 sm:px-4">
                    <nav class="flex gap-1">
                        <button
                            v-for="tab in workspaceTabs"
                            :key="tab.id"
                            type="button"
                            class="rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="workspaceTabClass(tab.id)"
                            @click="activeTab = tab.id"
                        >
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <div class="p-4 sm:p-5">
                    <div v-if="activeTab === 'overview'" class="space-y-4">
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-semibold text-slate-950">{{ t('Tier ladder') }}</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ t('Spend in the current calendar month unlocks timed discounts.') }}
                            </p>

                            <div v-if="launchTiers.length === 0" class="mt-4 rounded-lg bg-slate-50 px-4 py-4 text-sm text-slate-600">
                                {{ t('No loyalty tiers have been configured yet.') }}
                            </div>

                            <div v-else class="mt-4 overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-3 py-2 font-medium">{{ t('Tier') }}</th>
                                            <th class="px-3 py-2 font-medium">{{ t('Monthly spend') }}</th>
                                            <th class="px-3 py-2 font-medium">{{ t('Discount') }}</th>
                                            <th class="px-3 py-2 font-medium">{{ t('Active for') }}</th>
                                            <th class="px-3 py-2 font-medium">{{ t('Customers') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="entry in launchTiers"
                                            :key="entry.tier.id"
                                            class="border-b border-slate-100 last:border-0"
                                        >
                                            <td class="px-3 py-3 font-medium text-slate-950">{{ entry.tier.name }}</td>
                                            <td class="px-3 py-3 text-slate-600">{{ requirementLabel(entry.monthly_spend) }}</td>
                                            <td class="px-3 py-3 text-slate-950">{{ formatDiscount(entry.benefit) }}</td>
                                            <td class="px-3 py-3 text-slate-600">{{ durationLabel(entry.duration_months) }}</td>
                                            <td class="px-3 py-3 text-slate-600">{{ entry.users_count }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <button
                                v-if="canEditProgram"
                                type="button"
                                class="mt-4 text-sm font-medium text-cyan-700 transition hover:text-cyan-900"
                                @click="activeTab = 'program'"
                            >
                                {{ t('Edit program') }} {{ forwardArrow }}
                            </button>
                        </article>

                        <div class="grid gap-4 xl:grid-cols-2">
                            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h3 class="text-sm font-semibold text-slate-950">{{ t('Recent loyalty changes') }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ t('Latest upgrade or downgrade events.') }}</p>

                                <div class="mt-4 space-y-2">
                                    <div
                                        v-if="dashboard.recent_history.length === 0"
                                        class="rounded-lg bg-slate-50 px-4 py-4 text-sm text-slate-600"
                                    >
                                        {{ t('No loyalty history entries yet.') }}
                                    </div>
                                    <div
                                        v-for="entry in dashboard.recent_history"
                                        :key="entry.id"
                                        class="rounded-lg border border-slate-200 px-4 py-3"
                                    >
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p class="text-sm font-medium text-slate-950">{{ entry.user.name || entry.user.email || t('Unknown user') }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ pretty(entry.action) }} · {{ entry.changed_at }}</p>
                                            </div>
                                            <p class="text-sm text-slate-600">{{ entry.from_tier || t('No tier') }} {{ forwardArrow }} {{ entry.to_tier || t('No tier') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h3 class="text-sm font-semibold text-slate-950">{{ t('Users per tier') }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ t('Recent customers in each loyalty tier.') }}</p>

                                <div class="mt-4 space-y-2">
                                    <div
                                        v-for="entry in recentUsers"
                                        :key="entry.profile_id"
                                        class="rounded-lg border border-slate-200 px-4 py-3"
                                    >
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p class="text-sm font-medium text-slate-950">{{ entry.user.name || entry.user.email || t('Unknown user') }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ entry.tier_name }} · {{ t('Level') }} {{ entry.tier_level }}</p>
                                            </div>
                                            <div class="text-right text-xs text-slate-500">
                                                <p>{{ t('Orders') }} {{ entry.completed_orders_count }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="recentUsers.length === 0"
                                        class="rounded-lg bg-slate-50 px-4 py-4 text-sm text-slate-600"
                                    >
                                        {{ t('No users in this tier yet.') }}
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div v-else class="space-y-5">
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-950">{{ t('Tier configuration') }}</h3>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ t('Each card combines the level name, monthly spend target, discount, and active duration.') }}
                                </p>
                            </div>

                            <div class="grid gap-4">
                                <form
                                    v-for="entry in launchTiers"
                                    :key="entry.tier.id"
                                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                                    @submit.prevent="saveProgramTier(entry)"
                                >
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-950">{{ entry.tier.name }}</p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ pretty(entry.tier.code) }} · {{ entry.users_count }} {{ t('customers') }}
                                            </p>
                                        </div>
                                        <button
                                            v-if="canEditProgram"
                                            type="submit"
                                            class="inline-flex shrink-0 items-center justify-center rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                                        >
                                            {{ t('Save tier') }}
                                        </button>
                                    </div>

                                    <div class="mt-4 grid gap-3 md:grid-cols-4">
                                        <label class="block text-sm">
                                            <span class="font-medium text-slate-700">{{ t('Display name') }}</span>
                                            <input v-model="entry.tier.name" type="text" :class="inputClass" :disabled="!canManageTiers">
                                        </label>
                                        <label class="block text-sm">
                                            <span class="font-medium text-slate-700">{{ t('Monthly spend target') }}</span>
                                            <input
                                                v-if="entry.rule"
                                                v-model="entry.rule.min_period_spend"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                :class="inputClass"
                                                :disabled="!canManageRules"
                                            >
                                        </label>
                                        <label class="block text-sm">
                                            <span class="font-medium text-slate-700">{{ t('Discount percentage') }}</span>
                                            <input
                                                v-if="entry.benefit"
                                                v-model="entry.benefit.value"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                :class="inputClass"
                                                :disabled="!canManageBenefits"
                                            >
                                        </label>
                                        <label class="block text-sm">
                                            <span class="font-medium text-slate-700">{{ t('Active for (months)') }}</span>
                                            <p class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                                {{ durationLabel(entry.duration_months) }}
                                            </p>
                                        </label>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
