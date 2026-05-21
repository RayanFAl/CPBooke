<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useAdminLocale } from '../../composables/useAdminLocale';

const searchParams = typeof window === 'undefined'
    ? new URLSearchParams()
    : new URLSearchParams(window.location.search);

const prefilledUserId = searchParams.get('user_id') ?? '';
const prefilledOrderId = searchParams.get('order_id') ?? '';
const prefilledCategory = searchParams.get('category') ?? '';
const prefilledPriority = searchParams.get('priority') ?? '';
const prefilledSubject = searchParams.get('subject') ?? '';
const prefilledFirstMessage = searchParams.get('first_message') ?? '';

const props = defineProps({
    customers: {
        type: Array,
        required: true,
    },
    orders: {
        type: Array,
        required: true,
    },
    agents: {
        type: Array,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
    priorities: {
        type: Array,
        required: true,
    },
});

const { t } = useAdminLocale();

const form = useForm({
    user_id: props.customers.some((customer) => String(customer.id) === String(prefilledUserId))
        ? prefilledUserId
        : (props.customers[0]?.id ?? ''),
    order_id: props.orders.some((order) => String(order.id) === String(prefilledOrderId))
        ? prefilledOrderId
        : '',
    category: props.categories.some((category) => category.name === prefilledCategory)
        ? prefilledCategory
        : (props.categories[0]?.name ?? 'booking_change'),
    priority: props.priorities.some((priority) => priority.name === prefilledPriority)
        ? prefilledPriority
        : (props.priorities[1]?.name ?? 'medium'),
    assigned_agent_id: '',
    subject: prefilledSubject,
    first_message: prefilledFirstMessage,
});

const filteredOrders = computed(() => {
    if (!form.user_id) {
        return props.orders;
    }

    return props.orders.filter((order) => String(order.user_id) === String(form.user_id));
});

const submit = () => {
    form.post(route('admin.support.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('Create Support Ticket')" />

    <AdminLayout
        title="Create Support Ticket"
        description="Open a new support case, optionally link it to an order, and capture the first customer-facing message."
    >
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                        {{ t('Ticket Intake') }}
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('New support case') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ t('Capture the customer context, optional order context, and the opening issue summary without introducing automation yet.') }}
                    </p>
                </div>

                <form class="mt-8 space-y-6" @submit.prevent="submit">
                    <div>
                        <InputLabel for="user_id" :value="t('Customer')" />
                        <select
                            id="user_id"
                            v-model="form.user_id"
                            class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option value="" disabled>{{ t('Select a customer') }}</option>
                            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                {{ customer.name }} - {{ customer.email }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.user_id" />
                    </div>

                    <div>
                        <InputLabel for="order_id" :value="t('Order (optional)')" />
                        <select
                            id="order_id"
                            v-model="form.order_id"
                            class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">{{ t('No linked order') }}</option>
                            <option v-for="order in filteredOrders" :key="order.id" :value="order.id">
                                {{ order.reference }}{{ order.customer ? ` - ${order.customer}` : '' }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.order_id" />
                    </div>

                    <div class="grid gap-6 md:grid-cols-3">
                        <div>
                            <InputLabel for="category" :value="t('Category')" />
                            <select
                                id="category"
                                v-model="form.category"
                                class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option v-for="category in categories" :key="category.name" :value="category.name">
                                    {{ t(category.label) }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.category" />
                        </div>

                        <div>
                            <InputLabel for="priority" :value="t('Priority')" />
                            <select
                                id="priority"
                                v-model="form.priority"
                                class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option v-for="priority in priorities" :key="priority.name" :value="priority.name">
                                    {{ t(priority.label) }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.priority" />
                        </div>

                        <div>
                            <InputLabel for="assigned_agent_id" :value="t('Assigned agent')" />
                            <select
                                id="assigned_agent_id"
                                v-model="form.assigned_agent_id"
                                class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">{{ t('Unassigned') }}</option>
                                <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                                    {{ agent.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.assigned_agent_id" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="subject" :value="t('Subject')" />
                        <input
                            id="subject"
                            v-model="form.subject"
                            type="text"
                            class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                        <InputError class="mt-2" :message="form.errors.subject" />
                    </div>

                    <div>
                        <InputLabel for="first_message" :value="t('First message')" />
                        <textarea
                            id="first_message"
                            v-model="form.first_message"
                            rows="7"
                            class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.first_message" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <PrimaryButton :disabled="form.processing" :class="{ 'opacity-25': form.processing }">
                            {{ t('Create ticket') }}
                        </PrimaryButton>
                        <Link
                            :href="route('admin.support.index')"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            {{ t('Cancel') }}
                        </Link>
                    </div>
                </form>
            </div>

            <aside class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Workflow notes') }}</h3>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <li>{{ t('New tickets always start in the open state.') }}</li>
                        <li>{{ t('Assignment remains manual in this slice.') }}</li>
                        <li>{{ t('The first message is stored both as ticket context and as the opening conversation entry.') }}</li>
                    </ul>
                </div>
            </aside>
        </section>
    </AdminLayout>
</template>