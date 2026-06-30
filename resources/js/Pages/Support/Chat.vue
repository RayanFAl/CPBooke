<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { getEcho } from '../../lib/echo';

const props = defineProps({
    chat: {
        type: Object,
        required: true,
    },
});

const echo = getEcho();
const tickets = ref([]);
const selectedTicketId = ref(props.chat.selected_ticket_id ?? null);
const activeTicket = ref(null);
const messages = ref([]);
const loadingTickets = ref(false);
const loadingMessages = ref(false);
const sendingMessage = ref(false);
const creatingConversation = ref(false);
const typingName = ref(null);
const composerText = ref('');
const composerFile = ref(null);
const composerFileInput = ref(null);
const typingStopTimer = ref(null);
let activeChannelName = null;

const createForm = ref({
    subject: '',
    category: 'technical_issue',
    priority: 'medium',
    message: '',
});

const statusLabel = (value) => String(value || '')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

const relativeTime = (value) => {
    if (!value) {
        return 'now';
    }

    const diff = new Date(value).getTime() - Date.now();
    const absSeconds = Math.abs(Math.round(diff / 1000));
    const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    if (absSeconds < 60) {
        return formatter.format(Math.round(diff / 1000), 'second');
    }

    const absMinutes = Math.abs(Math.round(diff / 60000));

    if (absMinutes < 60) {
        return formatter.format(Math.round(diff / 60000), 'minute');
    }

    const absHours = Math.abs(Math.round(diff / 3600000));

    if (absHours < 24) {
        return formatter.format(Math.round(diff / 3600000), 'hour');
    }

    return formatter.format(Math.round(diff / 86400000), 'day');
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const apiFetch = async (url, options = {}) => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            ...(options.headers || {}),
        },
        ...options,
    });

    const payload = await response.json();

    if (!response.ok || payload.success === false) {
        throw new Error(payload.message || 'Request failed.');
    }

    return payload;
};

const sortedTickets = computed(() => [...tickets.value].sort((left, right) => {
    const leftDate = left.last_message_at || '';
    const rightDate = right.last_message_at || '';

    return rightDate.localeCompare(leftDate);
}));

const hasTickets = computed(() => tickets.value.length > 0);

const unreadTicketsCount = computed(() => tickets.value.filter((ticket) => ticket.unread_count > 0).length);

const isCreatingNewConversation = computed(() => !selectedTicketId.value);

const selectedTicketRecord = computed(() => tickets.value.find((ticket) => ticket.id === selectedTicketId.value) ?? activeTicket.value);

const groupedMessages = computed(() => messages.value.reduce((groups, message) => {
    const lastGroup = groups[groups.length - 1] ?? null;
    const senderKey = `${message.sender_type}:${message.sender?.id ?? 'system'}`;

    if (!lastGroup || lastGroup.senderKey !== senderKey) {
        groups.push({
            id: `group-${message.id}`,
            senderKey,
            sender_type: message.sender_type,
            sender: message.sender,
            messages: [message],
        });

        return groups;
    }

    lastGroup.messages.push(message);

    return groups;
}, []));

const attachmentLabel = computed(() => composerFile.value?.name || 'Attach file');

const loadTickets = async () => {
    loadingTickets.value = true;

    try {
        const payload = await apiFetch(props.chat.routes.tickets_index);
        tickets.value = payload.data.tickets ?? [];

        if (!selectedTicketId.value && tickets.value.length > 0) {
            selectedTicketId.value = tickets.value[0].id;
        }
    } finally {
        loadingTickets.value = false;
    }
};

const markSeen = async () => {
    if (!selectedTicketId.value) {
        return;
    }

    const unseenAgentIds = messages.value
        .filter((message) => message.sender_type === 'agent' && !message.seen_at)
        .map((message) => message.id);

    if (!unseenAgentIds.length) {
        return;
    }

    const payload = await apiFetch(`/api/v1/support/chat/tickets/${selectedTicketId.value}/seen`, {
        method: 'POST',
        body: JSON.stringify({ message_ids: unseenAgentIds }),
    });

    activeTicket.value = payload.data.ticket ?? activeTicket.value;
    tickets.value = tickets.value.map((ticket) => ticket.id === payload.data.ticket?.id ? payload.data.ticket : ticket);
    messages.value = messages.value.map((message) => unseenAgentIds.includes(message.id)
        ? { ...message, seen_at: new Date().toISOString() }
        : message);
};

const loadMessages = async (ticketId) => {
    if (!ticketId) {
        activeTicket.value = null;
        messages.value = [];
        return;
    }

    loadingMessages.value = true;

    try {
        const payload = await apiFetch(`/api/v1/support/chat/tickets/${ticketId}/messages`);
        activeTicket.value = payload.data.ticket;
        messages.value = payload.data.messages ?? [];
        tickets.value = tickets.value.map((ticket) => ticket.id === ticketId ? payload.data.ticket : ticket);
        await markSeen();
    } finally {
        loadingMessages.value = false;
    }
};

const syncTicketInList = (ticket) => {
    const existing = tickets.value.findIndex((entry) => entry.id === ticket.id);

    if (existing === -1) {
        tickets.value = [ticket, ...tickets.value];
        return;
    }

    tickets.value[existing] = ticket;
    tickets.value = [...tickets.value];
};

const leaveActiveChannel = () => {
    if (!activeChannelName || !echo) {
        return;
    }

    echo.leave(activeChannelName);
    activeChannelName = null;
};

const subscribeToTicket = (ticketId) => {
    leaveActiveChannel();

    if (!echo || !ticketId) {
        return;
    }

    activeChannelName = `support.ticket.${ticketId}`;
    const channel = echo.private(activeChannelName);

    channel.listen('.support.message.broadcasted', async (event) => {
        if (event?.message) {
            const exists = messages.value.some((message) => message.id === event.message.id);

            if (!exists && selectedTicketId.value === ticketId) {
                messages.value = [...messages.value, event.message];
            }
        }

        if (event?.ticket) {
            activeTicket.value = event.ticket;
            syncTicketInList(event.ticket);
        }

        await markSeen();
    });

    channel.listen('.support.ticket.updated', (event) => {
        if (event?.ticket) {
            activeTicket.value = event.ticket;
            syncTicketInList(event.ticket);
        }
    });

    channel.listen('.support.typing.broadcasted', (event) => {
        if (event?.typing?.sender_type !== 'agent') {
            return;
        }

        typingName.value = event.typing.sender?.name || 'Support';

        if (typingStopTimer.value) {
            window.clearTimeout(typingStopTimer.value);
        }

        typingStopTimer.value = window.setTimeout(() => {
            typingName.value = null;
        }, 3000);
    });
};

const sendTyping = async (typing) => {
    if (!selectedTicketId.value) {
        return;
    }

    try {
        await apiFetch(`/api/v1/support/chat/tickets/${selectedTicketId.value}/typing`, {
            method: 'POST',
            body: JSON.stringify({ typing }),
        });
    } catch {
        // Ignore transport-only typing failures.
    }
};

const queueTyping = () => {
    if (!selectedTicketId.value) {
        return;
    }

    sendTyping(true);

    if (typingStopTimer.value) {
        window.clearTimeout(typingStopTimer.value);
    }

    typingStopTimer.value = window.setTimeout(() => {
        sendTyping(false);
    }, 1600);
};

const handleComposerFile = (event) => {
    composerFile.value = event.target.files?.[0] ?? null;
};

const sendMessage = async () => {
    if (!selectedTicketId.value || sendingMessage.value || (!composerText.value.trim() && !composerFile.value)) {
        return;
    }

    sendingMessage.value = true;

    try {
        const formData = new FormData();

        if (composerText.value.trim()) {
            formData.append('message', composerText.value.trim());
        }

        if (composerFile.value) {
            formData.append('attachment', composerFile.value);
        }

        const payload = await apiFetch(`/api/v1/support/chat/tickets/${selectedTicketId.value}/messages`, {
            method: 'POST',
            body: formData,
        });

        activeTicket.value = payload.data.ticket;
        syncTicketInList(payload.data.ticket);

        if (payload.data.message) {
            messages.value = [...messages.value, payload.data.message];
        }

        composerText.value = '';
        composerFile.value = null;

        if (composerFileInput.value) {
            composerFileInput.value.value = '';
        }
    } finally {
        sendingMessage.value = false;
    }
};

const createConversation = async () => {
    if (creatingConversation.value || !createForm.value.subject.trim() || !createForm.value.message.trim()) {
        return;
    }

    creatingConversation.value = true;

    try {
        const payload = await apiFetch(props.chat.routes.tickets_create_or_reuse, {
            method: 'POST',
            body: JSON.stringify({
                category: createForm.value.category,
                priority: createForm.value.priority,
                subject: createForm.value.subject.trim(),
                message: createForm.value.message.trim(),
            }),
        });

        await loadTickets();
        selectedTicketId.value = payload.data.ticket.id;
        createForm.value = {
            subject: '',
            category: 'technical_issue',
            priority: 'medium',
            message: '',
        };
    } finally {
        creatingConversation.value = false;
    }
};

watch(selectedTicketId, async (ticketId) => {
    typingName.value = null;
    subscribeToTicket(ticketId);
    await loadMessages(ticketId);
});

onMounted(async () => {
    await loadTickets();

    if (selectedTicketId.value) {
        subscribeToTicket(selectedTicketId.value);
        await loadMessages(selectedTicketId.value);
    }
});

onBeforeUnmount(() => {
    leaveActiveChannel();

    if (typingStopTimer.value) {
        window.clearTimeout(typingStopTimer.value);
    }
});
</script>

<template>
    <Head title="Support Chat" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-slate-950">Support Chat</h2>
                    <p class="mt-1 text-sm text-slate-500">Chat with support from the web using the same live support API.</p>
                </div>
                <span class="inline-flex rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-rose-700">{{ unreadTicketsCount }} unread</span>
            </div>
        </template>

        <div class="bg-slate-100 py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                    <aside class="space-y-4">
                        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">Inbox</p>
                                    <h3 class="mt-2 text-lg font-semibold text-slate-950">Your conversations</h3>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
                                    @click="selectedTicketId = null"
                                >
                                    New chat
                                </button>
                            </div>
                        </div>

                        <div class="max-h-[72vh] overflow-y-auto rounded-[1.75rem] border border-slate-200 bg-white p-3 shadow-sm">
                            <div v-if="loadingTickets" class="px-4 py-8 text-sm text-slate-500">Loading conversations…</div>
                            <div v-else-if="!hasTickets" class="px-4 py-8 text-sm text-slate-500">No conversations yet. Start a new support chat.</div>
                            <div v-else class="space-y-3">
                                <button
                                    v-for="ticket in sortedTickets"
                                    :key="ticket.id"
                                    type="button"
                                    class="block w-full rounded-[1.5rem] border px-4 py-4 text-left transition"
                                    :class="ticket.id === selectedTicketId ? 'border-cyan-200 bg-cyan-50/80 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50'"
                                    @click="selectedTicketId = ticket.id"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-950">{{ ticket.subject }}</p>
                                            <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ ticket.code }}</p>
                                        </div>
                                        <span v-if="ticket.unread_count > 0" class="inline-flex min-w-7 items-center justify-center rounded-full bg-rose-500 px-2 py-1 text-[11px] font-semibold text-white">{{ ticket.unread_count }}</span>
                                    </div>

                                    <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold uppercase tracking-[0.16em]">
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ statusLabel(ticket.status) }}</span>
                                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-700">{{ statusLabel(ticket.priority) }}</span>
                                    </div>

                                    <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-500">
                                        <span>{{ statusLabel(ticket.conversation_state) }}</span>
                                        <span>{{ relativeTime(ticket.last_message_at) }}</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </aside>

                    <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                        <div v-if="isCreatingNewConversation" class="p-6 sm:p-8">
                            <div class="max-w-2xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">Start Support Chat</p>
                                <h3 class="mt-2 text-2xl font-semibold text-slate-950">Open a new conversation</h3>
                                <p class="mt-3 text-sm leading-6 text-slate-600">This creates or reuses your live support ticket through the same support API used by the mobile client.</p>
                            </div>

                            <div class="mt-8 grid gap-4 md:grid-cols-2">
                                <label class="space-y-2 text-sm font-medium text-slate-700 md:col-span-2">
                                    <span>Subject</span>
                                    <input v-model="createForm.subject" type="text" class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500">
                                </label>

                                <label class="space-y-2 text-sm font-medium text-slate-700">
                                    <span>Category</span>
                                    <select v-model="createForm.category" class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500">
                                        <option value="booking_change">Booking Change</option>
                                        <option value="refund_request">Refund Request</option>
                                        <option value="technical_issue">Technical Issue</option>
                                        <option value="payment_issue">Payment Issue</option>
                                        <option value="document_request">Document Request</option>
                                    </select>
                                </label>

                                <label class="space-y-2 text-sm font-medium text-slate-700">
                                    <span>Priority</span>
                                    <select v-model="createForm.priority" class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500">
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </label>

                                <label class="space-y-2 text-sm font-medium text-slate-700 md:col-span-2">
                                    <span>Message</span>
                                    <textarea v-model="createForm.message" rows="6" class="block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm leading-6 text-slate-900 outline-none transition focus:border-cyan-500" />
                                </label>

                                <div class="md:col-span-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                                        :disabled="creatingConversation"
                                        @click="createConversation"
                                    >
                                        {{ creatingConversation ? 'Opening…' : 'Open live chat' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-else class="flex h-[72vh] flex-col">
                            <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">Active Chat</p>
                                    <h3 class="mt-2 text-xl font-semibold text-slate-950">{{ selectedTicketRecord?.subject }}</h3>
                                    <p class="mt-2 text-sm text-slate-500">{{ selectedTicketRecord?.code }} · {{ statusLabel(selectedTicketRecord?.status) }}</p>
                                </div>
                            </div>

                            <div class="flex-1 overflow-y-auto bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.08),transparent_24%),linear-gradient(180deg,#f8fafc_0%,#f8fafc_40%,#ffffff_100%)] px-5 py-6 sm:px-6">
                                <div v-if="loadingMessages" class="text-sm text-slate-500">Loading messages…</div>
                                <div v-else-if="messages.length === 0" class="rounded-2xl bg-white/70 px-4 py-4 text-sm text-slate-500">No messages yet.</div>
                                <div v-else class="space-y-6">
                                    <article v-for="group in groupedMessages" :key="group.id" class="flex" :class="group.sender_type === 'customer' ? 'justify-end' : 'justify-start'">
                                        <div class="max-w-[85%] space-y-2">
                                            <p class="px-2 text-xs text-slate-500" :class="group.sender_type === 'customer' ? 'text-right' : 'text-left'">
                                                {{ group.sender?.name || 'Support' }} · {{ relativeTime(group.messages[group.messages.length - 1]?.created_at) }}
                                            </p>
                                            <div class="space-y-2">
                                                <div
                                                    v-for="message in group.messages"
                                                    :key="message.id"
                                                    class="rounded-[1.5rem] px-4 py-3 shadow-sm ring-1"
                                                    :class="group.sender_type === 'customer' ? 'bg-slate-950 text-white ring-slate-950' : 'bg-white text-slate-900 ring-slate-200'"
                                                >
                                                    <p v-if="message.text" class="whitespace-pre-line text-sm leading-6">{{ message.text }}</p>
                                                    <a
                                                        v-if="message.attachment?.url"
                                                        :href="message.attachment.url"
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        class="mt-3 inline-flex rounded-xl border px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em]"
                                                        :class="group.sender_type === 'customer' ? 'border-white/20 text-white' : 'border-slate-200 text-slate-700'"
                                                    >
                                                        {{ message.attachment.name || 'Attachment' }}
                                                    </a>
                                                    <p class="mt-2 text-[11px]" :class="group.sender_type === 'customer' ? 'text-slate-300' : 'text-slate-500'">
                                                        {{ new Date(message.created_at).toLocaleString() }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </div>

                            <div class="border-t border-slate-200 bg-white px-5 py-4 sm:px-6">
                                <div v-if="typingName" class="mb-3 rounded-2xl bg-cyan-50 px-4 py-3 text-sm text-cyan-700">{{ typingName }} is typing…</div>

                                <div v-if="selectedTicketRecord?.status === 'closed'" class="rounded-2xl bg-amber-50 px-4 py-4 text-sm text-amber-800">
                                    This conversation is closed. Start a new chat if you need more help.
                                </div>

                                <form v-else class="rounded-[1.5rem] border border-slate-200 p-3 shadow-sm" @submit.prevent="sendMessage">
                                    <div v-if="composerFile" class="mb-3 flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                        <span class="truncate">{{ composerFile.name }}</span>
                                        <button type="button" class="text-slate-500" @click="composerFile = null; composerFileInput.value = ''">Remove</button>
                                    </div>

                                    <div class="flex items-end gap-3">
                                        <button type="button" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-slate-200" @click="composerFileInput?.click()">
                                            +
                                        </button>
                                        <input ref="composerFileInput" type="file" class="hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt" @change="handleComposerFile">
                                        <textarea
                                            v-model="composerText"
                                            rows="1"
                                            class="block min-h-[48px] flex-1 resize-none rounded-[1.25rem] border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-900 outline-none transition focus:border-cyan-500 focus:bg-white"
                                            placeholder="Write your message"
                                            @input="queueTyping"
                                        />
                                        <button type="submit" class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60" :disabled="sendingMessage || (!composerText.trim() && !composerFile)">
                                            {{ sendingMessage ? 'Sending…' : 'Send' }}
                                        </button>
                                    </div>

                                    <p class="mt-3 text-xs text-slate-500">{{ composerFile ? attachmentLabel : 'Images and documents are supported.' }}</p>
                                </form>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>