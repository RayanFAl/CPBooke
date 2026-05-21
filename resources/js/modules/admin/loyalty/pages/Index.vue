<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminModulePage from '../../components/AdminModulePage.vue';
import LoyaltySettingsPanel from '../components/LoyaltySettingsPanel.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    settings: {
        type: Object,
        default: null,
    },
    canManageSettings: {
        type: Boolean,
        default: false,
    },
    settingsUpdateUrl: {
        type: String,
        default: null,
    },
});

const { t } = useAdminLocale();

const pretty = (value) => {
    if (value === null || value === undefined || value === '') {
        return t('Not available');
    }

    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

const updateTier = (tier) => {
    useForm({
        code: tier.code,
        name: tier.name,
        description: tier.description ?? '',
        badge_label: tier.badge_label ?? '',
        color_token: tier.color_token ?? '',
        sort_order: tier.sort_order,
        is_active: tier.is_active,
        is_default: tier.is_default,
    }).put(route('admin.loyalty.tiers.update', tier.id), {
        preserveScroll: true,
    });
};

const updateRule = (rule) => {
    useForm({
        name: rule.name,
        rule_type: 'upgrade',
        min_completed_orders: rule.min_completed_orders,
        min_lifetime_spend: rule.min_lifetime_spend,
        min_period_orders: rule.min_period_orders,
        min_period_spend: rule.min_period_spend,
        period_days: rule.period_days,
        allow_downgrade: rule.allow_downgrade,
        is_active: rule.is_active,
        priority: rule.priority,
    }).put(route('admin.loyalty.rules.update', rule.id), {
        preserveScroll: true,
    });
};

const updateBenefit = (benefit) => {
    useForm({
        name: benefit.name,
        description: benefit.description ?? '',
        benefit_type: benefit.benefit_type,
        value_type: benefit.value_type,
        value: benefit.value === '' ? null : benefit.value,
        display_order: benefit.display_order,
        is_highlighted: benefit.is_highlighted,
        is_active: benefit.is_active,
    }).put(route('admin.loyalty.benefits.update', benefit.id), {
        preserveScroll: true,
    });
};

const metrics = computed(() => [
    {
        key: 'profiles',
        label: t('Profiles'),
        value: props.dashboard.metrics.profiles,
        tone: 'text-slate-950',
    },
    {
        key: 'upgrades_last_30_days',
        label: t('Upgrades in 30 days'),
        value: props.dashboard.metrics.upgrades_last_30_days,
        tone: 'text-cyan-700',
    },
    {
        key: 'average_lifetime_spend',
        label: t('Average lifetime spend'),
        value: props.dashboard.metrics.average_lifetime_spend,
        tone: 'text-emerald-700',
    },
    {
        key: 'average_completed_orders',
        label: t('Average completed orders'),
        value: props.dashboard.metrics.average_completed_orders,
        tone: 'text-slate-950',
    },
]);
const recentUsers = computed(() => props.dashboard.users_per_tier.flatMap((bucket) => bucket.users.map((entry) => ({
    ...entry,
    tier_name: bucket.tier.name,
    tier_level: bucket.tier.level,
}))).slice(0, 6));
</script>

<template>
    <AdminLayout :title="t('Loyalty')" :description="t('Manage the tiered loyalty engine, dynamic rules, and customer privilege model.')">
        <AdminModulePage :eyebrow="t('Retention')" :title="t('Loyalty')" :description="t('Booking.com Genius-style tiering built on bookings, spend, and recent activity instead of wallet points.')">
            <div class="space-y-6">
                <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(6,182,212,0.12),_transparent_35%),linear-gradient(180deg,_#ffffff,_#f8fafc)] px-6 py-6">
                        <div class="max-w-3xl">
                            <p class="text-xs font-semibold uppercase tracking-[0.26em] text-cyan-700">{{ t('Loyalty management') }}</p>
                            <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ t('Loyalty program settings and tiers') }}</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                {{ t('Use this page to control global loyalty settings, edit tiers, define qualification rules, manage benefits, and review recent tier changes.') }}
                            </p>
                        </div>

                        <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <article v-for="metric in metrics" :key="metric.key" class="rounded-[1.4rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ metric.label }}</p>
                                <p class="mt-2 text-2xl font-semibold" :class="metric.tone">{{ metric.value }}</p>
                            </article>
                        </div>

                        <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-[1.4rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Step 1') }}</p>
                                <p class="mt-2 text-lg font-semibold text-slate-950">{{ t('Global settings') }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ t('Enable or disable the loyalty program and control global discount limits.') }}</p>
                            </div>
                            <div class="rounded-[1.4rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Step 2') }}</p>
                                <p class="mt-2 text-lg font-semibold text-slate-950">{{ t('Tiers') }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ t('Define the customer levels shown across the loyalty program.') }}</p>
                            </div>
                            <div class="rounded-[1.4rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Step 3') }}</p>
                                <p class="mt-2 text-lg font-semibold text-slate-950">{{ t('Rules') }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ t('Set when a customer qualifies for each tier based on orders and spend.') }}</p>
                            </div>
                            <div class="rounded-[1.4rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Step 4') }}</p>
                                <p class="mt-2 text-lg font-semibold text-slate-950">{{ t('Benefits') }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ t('Choose which discounts or service perks each tier receives.') }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                    <section class="space-y-6">
                        <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Program status') }}</p>
                                    <h3 class="mt-2 text-2xl font-semibold text-slate-950">{{ canManageSettings && settings?.loyalty_enabled ? t('Enabled') : t('Review settings below') }}</h3>
                                    <p class="mt-2 text-sm text-slate-600">{{ t('Check whether the loyalty program is active and whether global discount controls are turned on.') }}</p>
                                </div>
                                <div class="rounded-2xl bg-cyan-50 px-4 py-3 text-sm font-medium text-cyan-700">
                                    {{ props.dashboard.tiers.filter((tier) => tier.is_active).length }} {{ t('Active tiers') }}
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-[1.2rem] border border-slate-200 bg-slate-50 px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Rules') }}</p>
                                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ dashboard.rules.length }}</p>
                                </div>
                                <div class="rounded-[1.2rem] border border-slate-200 bg-slate-50 px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Benefits') }}</p>
                                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ dashboard.benefits.length }}</p>
                                </div>
                            </div>
                        </article>

                        <LoyaltySettingsPanel
                            v-if="canManageSettings"
                            :initial-settings="settings"
                            :can-manage="canManageSettings"
                            :update-url="settingsUpdateUrl"
                        />

                        <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Recent loyalty changes') }}</p>
                            <h3 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('Recent customer activity') }}</h3>
                            <p class="mt-2 text-sm text-slate-600">{{ t('Review the most recent users in each tier and the latest upgrade or downgrade events.') }}</p>

                            <div class="mt-5 space-y-3">
                                <div v-if="dashboard.recent_history.length === 0" class="rounded-[1.4rem] border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                    {{ t('No loyalty history entries yet.') }}
                                </div>
                                <div v-for="entry in dashboard.recent_history" :key="entry.id" class="rounded-[1.4rem] border border-slate-200 bg-slate-50 px-4 py-4">
                                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <p class="font-semibold text-slate-950">{{ entry.user.name || entry.user.email || t('Unknown user') }}</p>
                                            <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ pretty(entry.action) }} · {{ entry.changed_at }}</p>
                                        </div>
                                        <p class="text-sm text-slate-600">{{ entry.from_tier || t('No tier') }} → {{ entry.to_tier || t('No tier') }}</p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </section>

                    <section class="space-y-6">
                        <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Tiers') }}</p>
                                    <h3 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('Manage loyalty tiers') }}</h3>
                                    <p class="mt-2 text-sm text-slate-600">{{ t('Edit the visible tier name, badge label, and tier identity used across the loyalty program.') }}</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-4 xl:grid-cols-2">
                            <form v-for="tier in dashboard.tiers" :key="tier.id" class="rounded-[1.8rem] border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="updateTier(tier)">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <p class="text-lg font-semibold text-slate-950">{{ tier.name }}</p>
                                        <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ pretty(tier.code) }} · {{ t('Level') }} {{ tier.level }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">{{ tier.users_count }} {{ t('users') }}</span>
                                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">{{ t('Save tier') }}</button>
                                    </div>
                                </div>

                                <div class="mt-5 grid gap-3 md:grid-cols-2">
                                    <label class="text-sm text-slate-600">
                                        <span class="block font-medium text-slate-700">{{ t('Name') }}</span>
                                        <input v-model="tier.name" type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                    </label>
                                    <label class="text-sm text-slate-600">
                                        <span class="block font-medium text-slate-700">{{ t('Badge label') }}</span>
                                        <input v-model="tier.badge_label" type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                    </label>
                                </div>
                            </form>
                            </div>
                        </article>

                        <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Rules') }}</p>
                                <h3 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('Tier qualification rules') }}</h3>
                                <p class="mt-2 text-sm text-slate-600">{{ t('Set the minimum order count and spend required for each tier.') }}</p>
                            </div>

                            <div class="mt-5 grid gap-4 xl:grid-cols-2">
                            <form v-for="rule in dashboard.rules" :key="rule.id" class="rounded-[1.8rem] border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="updateRule(rule)">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-lg font-semibold text-slate-950">{{ rule.tier_name }}</p>
                                        <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ rule.name }}</p>
                                    </div>
                                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">{{ t('Save rule') }}</button>
                                </div>

                                <div class="mt-5 grid gap-3 md:grid-cols-2">
                                    <label class="text-sm text-slate-600">
                                        <span class="block font-medium text-slate-700">{{ t('Min completed orders') }}</span>
                                        <input v-model="rule.min_completed_orders" type="number" min="0" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                    </label>
                                    <label class="text-sm text-slate-600">
                                        <span class="block font-medium text-slate-700">{{ t('Min lifetime spend') }}</span>
                                        <input v-model="rule.min_lifetime_spend" type="number" min="0" step="0.01" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                    </label>
                                    <label class="text-sm text-slate-600">
                                        <span class="block font-medium text-slate-700">{{ t('Min period orders') }}</span>
                                        <input v-model="rule.min_period_orders" type="number" min="0" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                    </label>
                                    <label class="text-sm text-slate-600">
                                        <span class="block font-medium text-slate-700">{{ t('Min period spend') }}</span>
                                        <input v-model="rule.min_period_spend" type="number" min="0" step="0.01" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                    </label>
                                </div>
                            </form>
                            </div>
                        </article>

                        <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Benefits') }}</p>
                                <h3 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('Manage tier benefits') }}</h3>
                                <p class="mt-2 text-sm text-slate-600">{{ t('Edit the customer-facing perks and discount values linked to each tier.') }}</p>
                            </div>

                            <div class="mt-5 grid gap-4 xl:grid-cols-2">
                            <form v-for="benefit in dashboard.benefits" :key="benefit.id" class="rounded-[1.8rem] border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="updateBenefit(benefit)">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-lg font-semibold text-slate-950">{{ benefit.name }}</p>
                                        <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ benefit.tier_name }} · {{ pretty(benefit.code) }}</p>
                                    </div>
                                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">{{ t('Save benefit') }}</button>
                                </div>

                                <div class="mt-5 grid gap-3 md:grid-cols-2">
                                    <label class="text-sm text-slate-600">
                                        <span class="block font-medium text-slate-700">{{ t('Name') }}</span>
                                        <input v-model="benefit.name" type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                    </label>
                                    <label class="text-sm text-slate-600">
                                        <span class="block font-medium text-slate-700">{{ t('Value') }}</span>
                                        <input v-model="benefit.value" type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                    </label>
                                </div>

                                <div class="mt-4 rounded-[1.4rem] bg-slate-50 px-4 py-4 text-sm text-slate-600">
                                    {{ benefit.description || t('No description configured.') }}
                                </div>
                            </form>
                            </div>
                        </article>

                        <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('History') }}</p>
                                <h3 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('Users per tier') }}</h3>
                                <p class="mt-2 text-sm text-slate-600">{{ t('Review the most recent users in each tier and the latest upgrade or downgrade events.') }}</p>
                            </div>

                            <div class="mt-5 space-y-3">
                                <div v-for="entry in recentUsers" :key="entry.profile_id" class="rounded-[1.4rem] border border-slate-200 bg-slate-50 px-4 py-4">
                                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <p class="font-semibold text-slate-950">{{ entry.user.name || entry.user.email || t('Unknown user') }}</p>
                                            <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ entry.tier_name }} · {{ t('Level') }} {{ entry.tier_level }}</p>
                                        </div>
                                        <div class="text-right text-xs uppercase tracking-[0.16em] text-slate-500">
                                            <p>{{ t('Spend') }} {{ entry.lifetime_spend }}</p>
                                            <p class="mt-1">{{ t('Orders') }} {{ entry.completed_orders_count }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="recentUsers.length === 0" class="rounded-[1.4rem] border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                    {{ t('No users in this tier yet.') }}
                                </div>
                            </div>
                        </article>
                    </section>
                </div>
            </div>
        </AdminModulePage>
    </AdminLayout>
</template>