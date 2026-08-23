<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useAdminLocale } from '../composables/useAdminLocale';

const page = usePage();
const { t } = useAdminLocale();

const open = ref(false);
const query = ref('');
const loading = ref(false);
const result = ref({ groups: {}, total: 0, query: '' });
const inputRef = ref(null);
const activeIndex = ref(-1);

let debounceTimer = null;

const canSearch = computed(() => (page.props.auth.user?.permissions ?? []).includes('search.view'));

const groupLabels = {
    orders: 'Orders',
    customers: 'Customers',
    support_tickets: 'Support Tickets',
    wallet_transactions: 'Wallet Transactions',
    settlements: 'Settlements',
    passengers: 'Passengers',
};

const flatResults = computed(() => {
    const items = [];

    Object.entries(result.value.groups ?? {}).forEach(([group, groupItems]) => {
        groupItems.forEach((item) => {
            items.push({
                ...item,
                group,
                groupLabel: groupLabels[group] || group,
            });
        });
    });

    return items;
});

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const fetchSuggestions = async (value) => {
    if (!canSearch.value) {
        return;
    }

    loading.value = true;

    try {
        const url = new URL(route('admin.search.suggest'), window.location.origin);

        if (value.trim()) {
            url.searchParams.set('q', value.trim());
        }

        const response = await fetch(url.toString(), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });

        if (!response.ok) {
            throw new Error('Search failed');
        }

        result.value = await response.json();
        activeIndex.value = flatResults.value.length ? 0 : -1;
    } catch {
        result.value = { groups: {}, total: 0, query: value };
        activeIndex.value = -1;
    } finally {
        loading.value = false;
    }
};

const scheduleFetch = (value) => {
    if (debounceTimer) {
        window.clearTimeout(debounceTimer);
    }

    debounceTimer = window.setTimeout(() => {
        fetchSuggestions(value);
    }, 250);
};

const openPalette = async () => {
    if (!canSearch.value) {
        return;
    }

    open.value = true;
    await nextTick();
    inputRef.value?.focus();
    scheduleFetch(query.value);
};

const closePalette = () => {
    open.value = false;
    query.value = '';
    result.value = { groups: {}, total: 0, query: '' };
    activeIndex.value = -1;
};

const openFullSearch = () => {
    const trimmed = query.value.trim();
    closePalette();
    router.visit(route('admin.search.index', trimmed ? { q: trimmed } : {}));
};

const activateResult = (item) => {
    if (!item?.url) {
        openFullSearch();
        return;
    }

    closePalette();
    router.visit(item.url);
};

const onGlobalKeydown = (event) => {
    if (!canSearch.value) {
        return;
    }

    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        openPalette();
        return;
    }

    if (!open.value) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        closePalette();
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();

        if (!flatResults.value.length) {
            return;
        }

        activeIndex.value = (activeIndex.value + 1) % flatResults.value.length;
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();

        if (!flatResults.value.length) {
            return;
        }

        activeIndex.value = activeIndex.value <= 0
            ? flatResults.value.length - 1
            : activeIndex.value - 1;
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();

        if (activeIndex.value >= 0 && flatResults.value[activeIndex.value]) {
            activateResult(flatResults.value[activeIndex.value]);
            return;
        }

        openFullSearch();
    }
};

watch(query, (value) => {
    if (!open.value) {
        return;
    }

    scheduleFetch(value);
});

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onGlobalKeydown);

    if (debounceTimer) {
        window.clearTimeout(debounceTimer);
    }
});

defineExpose({ openPalette });
</script>

<template>
    <div v-if="canSearch" class="shrink-0">
        <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 sm:hidden"
            :aria-label="t('Open search')"
            @click="openPalette"
        >
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.5 3a5.5 5.5 0 104.383 8.823l3.09 3.09a.75.75 0 101.06-1.06l-3.09-3.09A5.5 5.5 0 008.5 3zm-4 5.5a4 4 0 118 0 4 4 0 01-8 0z" clip-rule="evenodd" />
            </svg>
        </button>

        <button
            type="button"
            class="hidden min-w-[10rem] items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 transition hover:border-slate-300 hover:bg-white hover:text-slate-700 sm:inline-flex lg:min-w-[14rem]"
            :aria-label="t('Open search')"
            @click="openPalette"
        >
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.5 3a5.5 5.5 0 104.383 8.823l3.09 3.09a.75.75 0 101.06-1.06l-3.09-3.09A5.5 5.5 0 008.5 3zm-4 5.5a4 4 0 118 0 4 4 0 01-8 0z" clip-rule="evenodd" />
            </svg>
            <span class="truncate">{{ t('Search everywhere…') }}</span>
            <kbd class="ms-auto hidden rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-semibold text-slate-400 lg:inline">Ctrl K</kbd>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                class="fixed inset-0 z-[120] bg-slate-950/40 p-4 backdrop-blur-sm sm:p-8"
                @click.self="closePalette"
            >
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-label="Global search"
                    class="mx-auto mt-8 w-full max-w-2xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl"
                >
                    <div class="border-b border-slate-100 px-4 py-3">
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="search"
                            class="w-full border-0 bg-transparent px-2 py-2 text-sm text-slate-900 outline-none placeholder:text-slate-400"
                            :placeholder="t('Booking ref, PNR, order id, phone, email, passport, ticket…')"
                        >
                    </div>

                    <div class="max-h-[24rem] overflow-y-auto px-2 py-2">
                        <p v-if="loading" class="px-4 py-6 text-sm text-slate-500">{{ t('Loading...') }}</p>

                        <p
                            v-else-if="query.trim().length > 0 && query.trim().length < 2"
                            class="px-4 py-6 text-sm text-slate-500"
                        >
                            {{ t('Enter at least 2 characters to search across operational records.') }}
                        </p>

                        <p
                            v-else-if="query.trim().length >= 2 && flatResults.length === 0"
                            class="px-4 py-6 text-sm text-slate-500"
                        >
                            {{ t('No quick results') }}
                        </p>

                        <template v-else>
                            <div
                                v-for="(items, group) in result.groups"
                                :key="group"
                                class="py-2"
                            >
                                <p class="px-4 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                                    {{ t(groupLabels[group] || group) }}
                                </p>

                                <button
                                    v-for="item in items"
                                    :key="`${item.type}-${item.id}`"
                                    type="button"
                                    class="block w-full rounded-2xl px-4 py-3 text-start transition"
                                    :class="flatResults[activeIndex]?.id === item.id && flatResults[activeIndex]?.type === item.type
                                        ? 'bg-cyan-50 text-slate-950'
                                        : 'hover:bg-slate-50'"
                                    @click="activateResult(item)"
                                >
                                    <div class="font-medium">{{ item.title }}</div>
                                    <div class="mt-1 text-sm text-slate-600">{{ item.subtitle || '—' }}</div>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-xs text-slate-500">
                        <span>{{ t('Use arrow keys to navigate, Enter to open') }}</span>
                        <button
                            type="button"
                            class="font-medium text-cyan-700 transition hover:text-cyan-800"
                            @click="openFullSearch"
                        >
                            {{ t('View all results') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
