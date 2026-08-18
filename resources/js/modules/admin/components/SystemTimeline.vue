<script setup>
import { computed, ref } from 'vue';
import { useAdminLocale } from '../composables/useAdminLocale';

const props = defineProps({
    events: { type: Array, default: () => [] },
    title: { type: String, default: 'System Timeline' },
    description: { type: String, default: 'Lifecycle events across approvals, wallets, settlements, and audit changes.' },
    emptyText: { type: String, default: 'No timeline events yet.' },
});

const { t } = useAdminLocale();
const query = ref('');

const toneClass = (tone) => {
    const map = {
        slate: 'bg-slate-100 text-slate-700',
        emerald: 'bg-emerald-50 text-emerald-700',
        amber: 'bg-amber-50 text-amber-800',
        violet: 'bg-violet-50 text-violet-700',
        cyan: 'bg-cyan-50 text-cyan-700',
        rose: 'bg-rose-50 text-rose-700',
    };

    return map[tone] || map.slate;
};

const filtered = computed(() => {
    const needle = query.value.trim().toLowerCase();
    const list = Array.isArray(props.events) ? props.events : [];

    if (!needle) {
        return list;
    }

    return list.filter((event) => {
        const blob = `${event.label || ''} ${event.description || ''} ${event.actor || ''} ${event.source || ''} ${event.category || ''}`.toLowerCase();
        return blob.includes(needle);
    });
});

const formatTime = (value) => {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};
</script>

<template>
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-950">{{ t(title) }}</h3>
                <p class="mt-1 text-sm text-slate-600">
                    {{ t(description) }}
                </p>
            </div>
            <label class="block w-full max-w-sm">
                <span class="sr-only">{{ t('Search timeline') }}</span>
                <input
                    v-model="query"
                    type="search"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none ring-cyan-600 focus:ring-2"
                    :placeholder="t('Search timeline, actor, event, or description')"
                >
            </label>
        </div>

        <p v-if="filtered.length === 0" class="mt-6 text-sm text-slate-500">
            {{ query ? t('No timeline events matched the current search.') : t(emptyText) }}
        </p>

        <ol v-else class="relative mt-6 space-y-4 border-s border-slate-200 ps-6">
            <li v-for="event in filtered" :key="event.key" class="relative">
                <span
                    class="absolute -start-[1.55rem] mt-1.5 h-3 w-3 rounded-full border-2 border-white"
                    :class="toneClass(event.tone).split(' ')[0]"
                />
                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-lg px-2 py-1 text-xs font-semibold" :class="toneClass(event.tone)">
                            {{ t(event.label) }}
                        </span>
                        <span v-if="event.category" class="rounded-lg bg-white px-2 py-1 text-xs font-medium capitalize text-slate-500">
                            {{ t(event.category) }}
                        </span>
                        <span class="text-xs text-slate-500">{{ formatTime(event.occurred_at) }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-700">{{ event.description || '—' }}</p>
                    <p class="mt-2 text-xs text-slate-500">
                        {{ t('Actor') }}: {{ event.actor || t('System') }}
                        <span v-if="event.source"> · {{ event.source }}</span>
                    </p>
                </div>
            </li>
        </ol>
    </div>
</template>
