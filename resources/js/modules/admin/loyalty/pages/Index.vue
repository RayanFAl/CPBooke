<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminModal from '../../components/AdminModal.vue';
import LoyaltyCompanyRatesPanel from '../components/LoyaltyCompanyRatesPanel.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
    settings: {
        type: Object,
        default: null,
    },
    settings_update_url: {
        type: String,
        default: '',
    },
    company_rates: {
        type: Object,
        default: null,
    },
    company_rates_sync_url: {
        type: String,
        default: '',
    },
    company_rates_url: {
        type: String,
        default: '',
    },
    can_manage_settings: {
        type: Boolean,
        default: false,
    },
    can_manage_company_rates: {
        type: Boolean,
        default: false,
    },
});

const { t } = useAdminLocale();
const page = usePage();
const programEnabled = ref(Boolean(props.program.loyalty_enabled));
const settingsState = ref(props.settings ?? null);
const isTogglingProgram = ref(false);
const toggleError = ref('');
const isSavingBasics = ref(false);
const isDuplicatingLevel = ref(false);
const editingTierId = ref(null);
const showAddLevel = ref(false);
const noteOpen = ref(false);
const ratesMatrix = ref(props.company_rates || {
    tiers: [],
    companies: [],
    rates: [],
    airlines: { count: 0, source: null, error: null },
});

const permissions = computed(() => page.props.auth.user?.permissions ?? []);
const canManageTiers = computed(() => permissions.value.includes('loyalty.manage'));
const canManageRules = computed(() => permissions.value.includes('loyalty.manage-rules'));
const canManageBenefits = computed(() => permissions.value.includes('loyalty.manage-benefits'));
const canEditProgram = computed(() => canManageTiers.value || canManageRules.value || canManageBenefits.value);
const canManageSettings = computed(
    () => props.can_manage_settings || permissions.value.includes('loyalty.settings.manage'),
);
const canManageCompanyRates = computed(
    () => props.can_manage_company_rates || permissions.value.includes('loyalty.manage-benefits'),
);

const primaryDiscountBenefit = (tierId) => props.dashboard.benefits.find(
    (benefit) => benefit.tier_id === tierId
        && benefit.benefit_type === 'discount'
        && benefit.is_active,
);

const primaryRule = (tierId) => props.dashboard.rules.find((rule) => rule.tier_id === tierId);

const launchTiers = computed(() => props.dashboard.tiers
    .filter((tier) => tier.level > 0)
    .map((tier) => {
        const rule = primaryRule(tier.id);
        const benefit = primaryDiscountBenefit(tier.id);

        return {
            tier,
            rule,
            benefit,
            users_count: tier.users_count,
            monthly_spend: rule?.min_period_spend ?? 0,
            duration_value: (() => {
                const unit = rule?.metadata?.benefit_duration_unit;
                const days = rule?.metadata?.benefit_duration_days;
                const months = rule?.metadata?.benefit_duration_months;

                if (unit === 'days' || (days && !months)) {
                    return days ?? '';
                }

                return months ?? '';
            })(),
            duration_unit: (() => {
                const unit = rule?.metadata?.benefit_duration_unit;

                if (unit === 'days' || unit === 'months') {
                    return unit;
                }

                if (rule?.metadata?.benefit_duration_days && !rule?.metadata?.benefit_duration_months) {
                    return 'days';
                }

                return 'months';
            })(),
            discount: benefit?.value ?? '',
        };
    }));

const editingEntry = computed(() => launchTiers.value.find(
    (entry) => entry.tier.id === editingTierId.value,
) ?? null);

const nextSuggestedLevel = computed(() => {
    const levels = launchTiers.value.map((entry) => Number(entry.tier.level) || 0);

    return (levels.length ? Math.max(...levels) : 0) + 1;
});

const newTierForm = useForm({
    name: '',
    discount_percentage: 3,
    monthly_spend: 0,
    duration_value: '',
    duration_unit: 'months',
    is_active: true,
    notify_customers: false,
});

watch(
    () => [newTierForm.duration_unit, newTierForm.duration_value],
    ([unit, value]) => {
        const days = value === '' || value === null ? 0 : Number(value);

        if (unit === 'days' && days > 0) {
            newTierForm.notify_customers = true;
        }
    },
);

const openEdit = (tierId) => {
    editingTierId.value = tierId;
};

const backToList = () => {
    editingTierId.value = null;
};

const openAddLevel = () => {
    showAddLevel.value = true;
};

const closeAddLevel = () => {
    showAddLevel.value = false;
};

const duplicateLevel = (entry) => {
    if (!canManageTiers.value || !entry?.tier || isDuplicatingLevel.value) {
        return;
    }

    isDuplicatingLevel.value = true;

    useForm({}).post(route('admin.loyalty.tiers.duplicate', entry.tier.id), {
        preserveScroll: true,
        onFinish: () => {
            isDuplicatingLevel.value = false;
        },
    });
};

const createTier = () => {
    if (!canManageTiers.value) {
        return;
    }

    newTierForm
        .transform((data) => {
            const value = data.duration_value === '' || data.duration_value === null
                ? null
                : Number(data.duration_value);
            const unit = data.duration_unit === 'days' ? 'days' : 'months';

            return {
                name: data.name?.trim() ? data.name.trim() : `Level ${nextSuggestedLevel.value}`,
                discount_percentage: data.discount_percentage === '' ? 0 : Number(data.discount_percentage),
                monthly_spend: data.monthly_spend === '' || data.monthly_spend === null ? 0 : Number(data.monthly_spend),
                duration_unit: unit,
                duration_days: unit === 'days' ? value : null,
                duration_months: unit === 'months' ? value : null,
                is_active: Boolean(data.is_active),
                notify_customers: Boolean(data.notify_customers),
            };
        })
        .post(route('admin.loyalty.tiers.store'), {
            preserveScroll: true,
            onSuccess: () => {
                newTierForm.reset();
                newTierForm.discount_percentage = 3;
                newTierForm.monthly_spend = 0;
                newTierForm.duration_value = '';
                newTierForm.duration_unit = 'months';
                newTierForm.is_active = true;
                newTierForm.notify_customers = false;
                showAddLevel.value = false;
            },
        });
};

const csrfToken = () => {
    if (typeof document === 'undefined') {
        return '';
    }

    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
};

const toggleLoyaltyProgram = async () => {
    if (!canManageSettings.value || !props.settings_update_url || isTogglingProgram.value) {
        return;
    }

    isTogglingProgram.value = true;
    toggleError.value = '';

    const nextEnabled = !programEnabled.value;
    const current = settingsState.value ?? props.settings ?? {};

    try {
        const response = await fetch(props.settings_update_url, {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                loyalty_enabled: nextEnabled,
                auto_upgrade_enabled: Boolean(current.auto_upgrade_enabled ?? true),
                auto_downgrade_enabled: Boolean(current.auto_downgrade_enabled ?? false),
                visible_in_mobile_app: Boolean(current.visible_in_mobile_app ?? true),
                allow_discount_stacking: Boolean(current.allow_discount_stacking ?? false),
                default_currency: String(current.default_currency ?? props.program.default_currency ?? 'LYD'),
                max_global_discount_amount: current.max_global_discount_amount ?? null,
                minimum_discountable_order_amount: current.minimum_discountable_order_amount ?? null,
                results_promo: current.results_promo ?? undefined,
            }),
        });

        const payload = await response.json();

        if (!response.ok || payload.success === false) {
            toggleError.value = t('Unable to save loyalty settings right now.');

            return;
        }

        programEnabled.value = Boolean(payload.data?.loyalty_enabled);
        if (payload.data) {
            settingsState.value = payload.data;
        }
    } catch {
        toggleError.value = t('Unable to save loyalty settings right now.');
    } finally {
        isTogglingProgram.value = false;
    }
};

const saveLevelBasics = (entry) => {
    if (!canEditProgram.value || !entry) {
        return;
    }

    isSavingBasics.value = true;
    let pending = 0;

    const done = () => {
        pending -= 1;
        if (pending <= 0) {
            isSavingBasics.value = false;
        }
    };

    if (canManageTiers.value) {
        pending += 1;
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
            onFinish: done,
        });
    }

    if (canManageRules.value && entry.rule) {
        pending += 1;
        const durationValue = entry.duration_value === '' || entry.duration_value === null
            ? null
            : Number(entry.duration_value);
        const durationUnit = entry.duration_unit === 'days' ? 'days' : 'months';

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
            benefit_duration_unit: durationUnit,
            benefit_duration_days: durationUnit === 'days' ? durationValue : null,
            benefit_duration_months: durationUnit === 'months' ? durationValue : null,
        }).put(route('admin.loyalty.rules.update', entry.rule.id), {
            preserveScroll: true,
            onFinish: done,
        });
    }

    if (canManageBenefits.value && entry.benefit) {
        pending += 1;
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
            onFinish: done,
        });
    }

    if (pending === 0) {
        isSavingBasics.value = false;
    }
};

const onRatesSaved = (matrix) => {
    if (matrix) {
        ratesMatrix.value = matrix;
    }
};

const requirementLabel = (monthlySpend) => {
    if (Number(monthlySpend) <= 0) {
        return t('On registration / login');
    }

    return `${monthlySpend} ${props.program.default_currency}`;
};

const durationLabel = (entry) => {
    const value = Number(entry?.duration_value);
    const unit = entry?.duration_unit === 'days' ? 'days' : 'months';

    if (!value) {
        return t('Permanent');
    }

    return unit === 'days'
        ? `${value} ${t('days')}`
        : `${value} ${t('months')}`;
};

const formatDiscount = (benefit) => {
    if (!benefit || benefit.value === '' || benefit.value === null) {
        return '—';
    }

    return `${benefit.value}%`;
};

const programStatusLabel = computed(() => (programEnabled.value ? t('Enabled') : t('Disabled')));
const inputClass = 'block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-400 disabled:bg-slate-50';
</script>

<template>
    <Head :title="t('Loyalty')" />

    <AdminLayout
        :title="t('Loyalty')"
        :description="t('Open a level to edit its details and company discounts.')"
    >
        <section class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-950">{{ t('Loyalty') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ editingEntry
                                ? t('Editing one level only — not a general table.')
                                : t('Level 1 starts automatically on registration or login with a permanent discount. Higher levels unlock from monthly spend and stay active for a fixed number of months.') }}
                        </p>
                    </div>

                    <div class="flex flex-col items-start gap-1 sm:items-end">
                        <button
                            type="button"
                            class="inline-flex items-center gap-3 rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-700 transition hover:bg-white disabled:opacity-60"
                            :disabled="!canManageSettings || isTogglingProgram"
                            :aria-pressed="programEnabled"
                            @click="toggleLoyaltyProgram"
                        >
                            <span class="font-medium">{{ t('Program') }}</span>
                            <span
                                class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                                :class="programEnabled ? 'bg-emerald-600' : 'bg-slate-300'"
                            >
                                <span
                                    class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition"
                                    :class="programEnabled ? 'start-5' : 'start-0.5'"
                                />
                            </span>
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ isTogglingProgram ? t('Saving...') : programStatusLabel }}
                            </span>
                        </button>
                        <p v-if="toggleError" class="text-xs text-rose-600">{{ toggleError }}</p>
                    </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-start transition hover:bg-slate-100/80"
                        :aria-expanded="noteOpen"
                        @click="noteOpen = !noteOpen"
                    >
                        <span class="text-xs font-semibold text-slate-800">
                            {{ t('Note') }} — {{ t('How the discount is calculated') }}
                        </span>
                        <span class="text-sm text-slate-500" aria-hidden="true">
                            {{ noteOpen ? '−' : '+' }}
                        </span>
                    </button>

                    <div v-show="noteOpen" class="border-t border-slate-200 px-4 py-3 text-xs leading-5 text-slate-600">
                        <ul class="list-disc space-y-1 ps-4">
                            <li>{{ t('Fare = ticket/base price only (before tax). This is the amount loyalty can discount.') }}</li>
                            <li>{{ t('Tax = taxes and fees. Loyalty never discounts tax.') }}</li>
                            <li>{{ t('Discount = Fare × membership percentage (example: Level 1 = 3%).') }}</li>
                            <li>{{ t('Final price = (Fare − Discount) + Tax') }}</li>
                            <li>{{ t('Welcome / Level 1 discount stays active until the first completed order, then it ends.') }}</li>
                        </ul>
                        <p class="mt-2 text-slate-700">
                            {{ t('Example: Fare 490, Tax 0, discount 3% → Discount 14.7 → Final ≈ 475.3. If Tax exists (e.g. Fare 613 + Tax 127 = 740), discount applies to 613 only, then tax is added back.') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- LIST -->
            <template v-if="!editingEntry">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-950">{{ t('Levels') }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ t('Click Edit to open one level.') }}</p>
                        </div>
                        <button
                            v-if="canManageTiers"
                            type="button"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-950 text-lg font-semibold leading-none text-white transition hover:bg-slate-800"
                            :aria-label="t('Add level')"
                            :title="t('Add level')"
                            @click="openAddLevel"
                        >
                            +
                        </button>
                    </div>

                    <div v-if="launchTiers.length === 0" class="px-5 py-8 text-sm text-slate-500">
                        {{ t('No loyalty tiers have been configured yet.') }}
                    </div>

                    <div v-else class="divide-y divide-slate-100">
                        <div
                            v-for="entry in launchTiers"
                            :key="entry.tier.id"
                            class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                            :class="entry.tier.is_active ? '' : 'bg-slate-50/80'"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-slate-950">{{ entry.tier.name }}</p>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="entry.tier.is_active
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-slate-200 text-slate-600'"
                                    >
                                        {{ entry.tier.is_active ? t('Active') : t('Inactive') }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ t('Discount') }} {{ formatDiscount(entry.benefit) }}
                                    · {{ requirementLabel(entry.monthly_spend) }}
                                    · {{ durationLabel(entry) }}
                                    · {{ entry.users_count }} {{ t('customers') }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    v-if="canManageTiers"
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 hover:text-slate-950 disabled:opacity-60"
                                    :aria-label="t('Copy level')"
                                    :title="t('Copy level')"
                                    :disabled="isDuplicatingLevel"
                                    @click="duplicateLevel(entry)"
                                >
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                        <path d="M7 3.5A1.5 1.5 0 018.5 2h6A1.5 1.5 0 0116 3.5v10a1.5 1.5 0 01-1.5 1.5h-6A1.5 1.5 0 017 13.5v-10z" />
                                        <path d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h6a1.5 1.5 0 001.5-1.5V16H8.5A2.5 2.5 0 016 13.5V6H4.5z" />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                                    @click="openEdit(entry.tier.id)"
                                >
                                    {{ t('Edit') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <AdminModal
                    :show="showAddLevel"
                    title="Add level"
                    max-width="xl"
                    @close="closeAddLevel"
                >
                    <form class="space-y-4" @submit.prevent="createTier">
                        <p class="text-sm text-slate-500">
                            {{ t('New level will be Level :level', { level: nextSuggestedLevel }) }}
                        </p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block text-sm">
                                <span class="mb-1 block text-slate-600">{{ t('Name') }}</span>
                                <input v-model="newTierForm.name" type="text" :class="inputClass" :placeholder="`Level ${nextSuggestedLevel}`">
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block text-slate-600">{{ t('Discount') }} %</span>
                                <input v-model="newTierForm.discount_percentage" type="number" min="0" max="100" step="0.01" :class="inputClass" required>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block text-slate-600">{{ t('Monthly spend') }}</span>
                                <input v-model="newTierForm.monthly_spend" type="number" min="0" step="0.01" :class="inputClass">
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block text-slate-600">{{ t('Duration') }}</span>
                                <input v-model="newTierForm.duration_value" type="number" min="0" step="1" :class="inputClass" :placeholder="t('Permanent')">
                            </label>
                            <label class="block text-sm sm:col-span-2">
                                <span class="mb-1 block text-slate-600">{{ t('Unit') }}</span>
                                <select v-model="newTierForm.duration_unit" :class="inputClass">
                                    <option value="days">{{ t('Days') }}</option>
                                    <option value="months">{{ t('Months') }}</option>
                                </select>
                            </label>
                        </div>

                        <label class="flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                            <input v-model="newTierForm.notify_customers" type="checkbox" class="mt-0.5 h-4 w-4">
                            <span>
                                {{ t('Send push notification to everyone about this discount') }}
                                <span class="mt-0.5 block text-xs text-slate-500">
                                    {{ t('Recommended for short campaigns (e.g. 3 days).') }}
                                </span>
                            </span>
                        </label>

                        <p v-if="Object.keys(newTierForm.errors).length" class="text-sm text-rose-600">
                            {{ Object.values(newTierForm.errors)[0] }}
                        </p>

                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                @click="closeAddLevel"
                            >
                                {{ t('Cancel') }}
                            </button>
                            <button
                                type="submit"
                                class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                                :disabled="newTierForm.processing"
                            >
                                {{ newTierForm.processing ? t('Saving...') : t('Add') }}
                            </button>
                        </div>
                    </form>
                </AdminModal>
            </template>

            <!-- EDIT ONE LEVEL -->
            <template v-else>
                <div class="flex items-center justify-between gap-3">
                    <button
                        type="button"
                        class="text-sm font-medium text-slate-600 transition hover:text-slate-950"
                        @click="backToList"
                    >
                        ← {{ t('Back to levels') }}
                    </button>
                    <p class="text-sm font-semibold text-slate-950">{{ editingEntry.tier.name }}</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-950">{{ t('Level details') }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ t('Basic settings for this level only.') }}</p>
                        </div>
                        <button
                            v-if="canEditProgram"
                            type="button"
                            class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                            :disabled="isSavingBasics"
                            @click="saveLevelBasics(editingEntry)"
                        >
                            {{ isSavingBasics ? t('Saving...') : t('Save details') }}
                        </button>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-600">{{ t('Name') }}</span>
                            <input v-model="editingEntry.tier.name" type="text" :class="inputClass" :disabled="!canManageTiers">
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-600">{{ t('Status') }}</span>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm transition hover:bg-slate-50 disabled:opacity-60"
                                :disabled="!canManageTiers"
                                @click="editingEntry.tier.is_active = !editingEntry.tier.is_active"
                            >
                                <span>{{ editingEntry.tier.is_active ? t('Active') : t('Inactive') }}</span>
                                <span
                                    class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                                    :class="editingEntry.tier.is_active ? 'bg-emerald-600' : 'bg-slate-300'"
                                >
                                    <span
                                        class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition"
                                        :class="editingEntry.tier.is_active ? 'start-5' : 'start-0.5'"
                                    />
                                </span>
                            </button>
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-600">{{ t('Monthly spend') }}</span>
                            <input
                                v-if="editingEntry.rule"
                                v-model="editingEntry.rule.min_period_spend"
                                type="number"
                                min="0"
                                step="0.01"
                                :class="inputClass"
                                :disabled="!canManageRules"
                            >
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-600">{{ t('Default discount') }} %</span>
                            <input
                                v-if="editingEntry.benefit"
                                v-model="editingEntry.benefit.value"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                :class="inputClass"
                                :disabled="!canManageBenefits"
                            >
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-600">{{ t('Duration') }}</span>
                            <input
                                v-if="editingEntry.rule"
                                v-model="editingEntry.duration_value"
                                type="number"
                                min="0"
                                step="1"
                                :class="inputClass"
                                :disabled="!canManageRules"
                                :placeholder="t('Permanent')"
                            >
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-slate-600">{{ t('Unit') }}</span>
                            <select
                                v-if="editingEntry.rule"
                                v-model="editingEntry.duration_unit"
                                :class="inputClass"
                                :disabled="!canManageRules"
                            >
                                <option value="days">{{ t('Days') }}</option>
                                <option value="months">{{ t('Months') }}</option>
                            </select>
                        </label>
                    </div>
                </div>

                <LoyaltyCompanyRatesPanel
                    :key="editingEntry.tier.id"
                    :tier-id="editingEntry.tier.id"
                    :initial-matrix="ratesMatrix"
                    :sync-url="company_rates_sync_url"
                    :show-url="company_rates_url"
                    :can-manage="canManageCompanyRates"
                    compact
                    @saved="onRatesSaved"
                />
            </template>
        </section>
    </AdminLayout>
</template>
