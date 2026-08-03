<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import OrderTicketPanel from '../../orders/components/OrderTicketPanel.vue';
import ResolutionReportForm from '../components/ResolutionReportForm.vue';
import ResolutionReportView from '../components/ResolutionReportView.vue';
import SystemTimeline from '../../components/SystemTimeline.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { getEcho } from '../../../../lib/echo';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    inbox: {
        type: Object,
        required: true,
    },
    status_options: {
        type: Array,
        required: true,
    },
    resolution_reports_enabled: {
        type: Boolean,
        required: true,
    },
    resolution_type_options: {
        type: Array,
        required: true,
    },
    resolution_status_options: {
        type: Array,
        required: true,
    },
    agents: {
        type: Array,
        required: true,
    },
    order_actions: {
        type: Object,
        required: true,
    },
    notification_logs_enabled: {
        type: Boolean,
        default: false,
    },
    customer_notification_logs: {
        type: Array,
        default: () => [],
    },
    system_timeline: {
        type: Array,
        default: () => [],
    },
});

const { locale, t } = useAdminLocale();
const page = usePage();
const permissions = computed(() => page.props.auth.user?.permissions ?? []);
const canViewUsers = computed(() => permissions.value.includes('users.view'));
const canViewSupport = computed(() => permissions.value.includes('support.view'));
const canViewOrders = computed(() => permissions.value.includes('orders.view'));
const canViewNotificationLogs = computed(() => permissions.value.includes('notifications.view'));
const echo = getEcho();
const typingParticipant = ref(null);
const typingTimeout = ref(null);

const inboxForm = useForm({
    search: props.inbox.filters?.search ?? '',
    status: props.inbox.filters?.status ?? '',
});

const replyForm = useForm({
    message: '',
    attachment: null,
});

const messagesViewport = ref(null);
const composerTextarea = ref(null);
const composerFileInput = ref(null);
const lightboxIndex = ref(null);
const touchStartX = ref(null);
const activeDocumentPreview = ref(null);
const activeOrderAction = ref(null);
const activeWorkspaceTab = ref('conversation');
const activityQuery = ref('');
const activityScope = ref('all');
const notificationFilter = ref('all');
const internalNotesExpanded = ref(true);
const customerDrawerOpen = ref(false);
const customerContextLoading = ref(false);
const customerContextLoaded = ref(false);
const customerContextError = ref('');
const customerContext = ref({
    country: null,
    recentOrdersCount: null,
    activeTicketCount: null,
    lastActivity: null,
});

const orderActionForm = useForm({
    reason: '',
    amount: '',
    internal_note: '',
    compensation_type: '',
});

const replyFileLabel = computed(() => replyForm.attachment?.name || t('No file selected'));

const conversationMessages = computed(() => props.ticket.messages.filter((message) => !message.is_internal));

const internalMessages = computed(() => props.ticket.messages.filter((message) => message.is_internal));

const latestMessageId = computed(() => conversationMessages.value[conversationMessages.value.length - 1]?.id ?? null);

const normalizeAttachmentUrl = (value) => {
    if (!value || typeof value !== 'string') {
        return null;
    }

    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')) {
        return value;
    }

    return `/${value.replace(/^\/+/, '')}`;
};

const attachmentSource = (message) => normalizeAttachmentUrl(message?.attachment_url);

const imageMessages = computed(() => props.ticket.messages.filter((message) => message.attachment_is_image && attachmentSource(message)));

const activeLightboxMessage = computed(() => {
    if (lightboxIndex.value === null) {
        return null;
    }

    return imageMessages.value[lightboxIndex.value] ?? null;
});

const groupedMessages = computed(() => {
    return conversationMessages.value.reduce((groups, message, index) => {
        const previousMessage = conversationMessages.value[index - 1] ?? null;
        const messageSenderKey = `${message.sender_type}:${message.user?.id ?? 'system'}`;
        const previousSenderKey = previousMessage
            ? `${previousMessage.sender_type}:${previousMessage.user?.id ?? 'system'}`
            : null;

        if (!groups.length || previousSenderKey !== messageSenderKey) {
            groups.push({
                id: `group-${message.id}`,
                sender_type: message.sender_type,
                user: message.user,
                senderKey: messageSenderKey,
                messages: [message],
            });

            return groups;
        }

        groups[groups.length - 1].messages.push(message);

        return groups;
    }, []);
});

const formatConversationStateLabel = (conversationState, status = null) => {
    if (status === 'resolved') {
        return t('Resolved');
    }

    if (status === 'in_progress') {
        return t('In progress');
    }

    if (conversationState === 'waiting_for_support') {
        return t('Waiting for support');
    }

    if (conversationState === 'waiting_for_customer') {
        return t('Waiting for customer');
    }

    return t('No conversation state');
};

const conversationStateLabel = computed(() => formatConversationStateLabel(props.ticket.conversation_state, props.ticket.status));

const conversationStateTone = computed(() => {
    if (props.ticket.status === 'resolved') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    }

    if (props.ticket.status === 'in_progress') {
        return 'bg-amber-50 text-amber-700 ring-amber-200';
    }

    if (props.ticket.conversation_state === 'waiting_for_support') {
        return 'bg-orange-50 text-orange-700 ring-orange-200';
    }

    if (props.ticket.conversation_state === 'waiting_for_customer') {
        return 'bg-slate-100 text-slate-700 ring-slate-200';
    }

    return 'bg-slate-100 text-slate-700 ring-slate-200';
});

const statusForm = useForm({
    status: props.ticket.status,
});

const assignmentForm = useForm({
    assigned_agent_id: props.ticket.assigned_agent_id ?? '',
});

const formatDateTime = (value) => {
    if (!value) {
        return t('Not available');
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const formatLabel = (value) => {
    if (!value) {
        return t('Not available');
    }

    const normalizedValue = value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

    return t(normalizedValue);
};

const formatMoney = (amount, currency = 'LYD') => {
    if (amount === null || amount === undefined) {
        return t('Not available');
    }

    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: currency || 'LYD',
    }).format(Number(amount));
};

const actorName = (entry) => entry.user?.name || entry.user?.email || t('System');

const initialsFor = (entry) => {
    const label = actorName(entry).trim();

    if (!label) {
        return 'SY';
    }

    return label
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
};

const avatarTone = (senderType) => (senderType === 'user'
    ? 'bg-slate-950 text-white'
    : 'bg-cyan-100 text-cyan-800');

const groupAlignment = (senderType) => (senderType === 'user' ? 'items-end' : 'items-start');

const groupRowAlignment = (senderType) => (senderType === 'user' ? 'flex-row-reverse' : 'flex-row');

const bubbleTone = (senderType, isInternal) => {
    if (isInternal) {
        return 'bg-amber-50 text-amber-950 ring-amber-200';
    }

    if (senderType === 'user') {
        return 'bg-slate-950 text-white ring-slate-950 shadow-slate-950/10';
    }

    return 'bg-white text-slate-900 ring-slate-200 shadow-slate-200/80';
};

const bubbleTextTone = (senderType, isInternal = false) => {
    if (isInternal) {
        return 'text-amber-900';
    }

    return senderType === 'user' ? 'text-slate-100' : 'text-slate-700';
};

const metaTone = (senderType, isInternal = false) => {
    if (isInternal) {
        return 'text-amber-700';
    }

    return senderType === 'user' ? 'text-slate-300' : 'text-slate-500';
};

const isImageAttachment = (message) => Boolean(message.attachment_is_image && attachmentSource(message));

const hasDownloadableAttachment = (message) => Boolean(message.has_attachment && attachmentSource(message));

const isPdfAttachment = (message) => Boolean(message?.attachment_mime?.includes('pdf') && attachmentSource(message));

const attachmentTypeLabel = (message) => {
    if (message.attachment_mime?.includes('pdf')) {
        return t('PDF');
    }

    if (message.attachment_mime?.includes('spreadsheet') || message.attachment_mime?.includes('excel')) {
        return t('Sheet');
    }

    if (message.attachment_mime?.includes('word') || message.attachment_mime?.includes('document')) {
        return t('Doc');
    }

    if (message.attachment_is_image) {
        return t('Image');
    }

    return t('File');
};

const formatAttachmentSize = (size) => {
    if (!size) {
        return t('File attached');
    }

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const relativeTime = (value) => {
    if (!value) {
        return t('now');
    }

    const diff = new Date(value).getTime() - Date.now();
    const absSeconds = Math.abs(Math.round(diff / 1000));
    const formatter = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' });

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

const composerPreviewUrl = computed(() => {
    if (!(replyForm.attachment instanceof File) || !replyForm.attachment.type.startsWith('image/')) {
        return null;
    }

    return URL.createObjectURL(replyForm.attachment);
});

watch(composerPreviewUrl, (nextValue, previousValue) => {
    if (previousValue) {
        URL.revokeObjectURL(previousValue);
    }
});

onBeforeUnmount(() => {
    if (composerPreviewUrl.value) {
        URL.revokeObjectURL(composerPreviewUrl.value);
    }
});

const latestSenderBadgeLabel = (message) => (message.sender_type === 'agent' ? t('Support') : t('Customer'));

const availableOrderActions = computed(() => props.order_actions?.available ?? []);

const selectedOrderActionConfig = computed(() => availableOrderActions.value.find((action) => action.name === activeOrderAction.value) ?? null);

const canManageOrderActions = computed(() => Boolean(props.order_actions?.can_manage && props.ticket.order));

const orderSnapshot = computed(() => props.ticket.order_snapshot ?? null);

const timelineEntries = computed(() => props.ticket.timeline ?? []);

const resolutionReport = computed(() => props.ticket.resolution_report ?? null);

const hasClosedResolutionReport = computed(() => resolutionReport.value?.status_after === 'closed');

const workspaceTabs = computed(() => [
    { id: 'conversation', label: t('Conversation') },
    ...(props.resolution_reports_enabled ? [{ id: 'resolution_report', label: t('Resolution') }] : []),
    { id: 'details', label: t('Details') },
    { id: 'activity', label: t('Activity') },
]);

const workspaceTabClass = (tabId) => (activeWorkspaceTab.value === tabId
    ? 'bg-slate-950 text-white'
    : 'text-slate-600 hover:bg-slate-100');

const changeTab = (tabId) => {
    activeWorkspaceTab.value = tabId;
};

const orderShowLink = computed(() => (props.ticket.order?.id
    ? route('admin.orders.show', props.ticket.order.id)
    : null));

const ticketCustomer = computed(() => props.ticket.user ?? null);

const customerProfileLink = computed(() => canViewUsers.value && ticketCustomer.value?.id
    ? route('admin.users.show', ticketCustomer.value.id)
    : null);

const customerOrdersLink = computed(() => (customerProfileLink.value
    ? `${customerProfileLink.value}#orders`
    : route('admin.orders.index')));

const relatedTicketsLink = computed(() => route('admin.support.index', {
    user_id: ticketCustomer.value?.id,
    ...(props.ticket.order?.id ? { order_id: props.ticket.order.id } : {}),
}));

const supportCreateLink = computed(() => route('admin.support.create', {
    user_id: ticketCustomer.value?.id,
    order_id: props.ticket.order?.id ?? undefined,
    category: props.ticket.category ?? 'general_inquiry',
    priority: props.ticket.priority ?? 'medium',
}));

const ticketHealth = computed(() => {
    if (['closed', 'resolved'].includes(props.ticket.status)) {
        return {
            label: t('OK'),
            tone: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            description: t('This ticket is no longer active.'),
            show: false,
        };
    }

    const now = Date.now();
    const resolutionDue = props.ticket.resolution_due_at ? new Date(props.ticket.resolution_due_at).getTime() : null;
    const firstResponseDue = props.ticket.first_response_due_at ? new Date(props.ticket.first_response_due_at).getTime() : null;

    if ((resolutionDue && now > resolutionDue) || (firstResponseDue && now > firstResponseDue && !props.ticket.first_response_at)) {
        return {
            label: t('Critical'),
            tone: 'bg-rose-50 text-rose-700 ring-rose-200',
            description: t('An SLA deadline has been missed for this ticket.'),
            show: true,
        };
    }

    const warningWindow = 2 * 60 * 60 * 1000;

    if (
        (resolutionDue && resolutionDue - now <= warningWindow)
        || (firstResponseDue && firstResponseDue - now <= warningWindow && !props.ticket.first_response_at)
    ) {
        return {
            label: t('Warning'),
            tone: 'bg-amber-50 text-amber-700 ring-amber-200',
            description: t('An SLA deadline is approaching for this ticket.'),
            show: true,
        };
    }

    return {
        label: t('OK'),
        tone: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        description: t('No active SLA blockers detected.'),
        show: false,
    };
});

const filteredNotificationLogs = computed(() => {
    if (notificationFilter.value === 'ticket') {
        return props.customer_notification_logs.filter((log) => log.is_ticket_related);
    }

    if (notificationFilter.value === 'order') {
        return props.customer_notification_logs.filter((log) => log.is_order_related);
    }

    return props.customer_notification_logs;
});

const activityItems = computed(() => {
    const items = [];

    if (activityScope.value === 'all' || activityScope.value === 'events') {
        for (const entry of timelineEntries.value) {
            items.push({
                kind: 'event',
                id: entry.id,
                created_at: entry.created_at,
                entry,
            });
        }
    }

    if (props.notification_logs_enabled && (activityScope.value === 'all' || activityScope.value === 'notifications')) {
        for (const log of filteredNotificationLogs.value) {
            items.push({
                kind: 'notification',
                id: `notification-${log.id}`,
                created_at: log.sent_at || log.failed_at || log.created_at,
                log,
            });
        }
    }

    return items.sort((left, right) => new Date(right.created_at).getTime() - new Date(left.created_at).getTime());
});

const filteredActivityItems = computed(() => {
    const query = activityQuery.value.trim().toLowerCase();

    if (!query) {
        return activityItems.value;
    }

    return activityItems.value.filter((item) => {
        if (item.kind === 'event') {
            const entry = item.entry;

            return `${entry.event} ${entry.description} ${entry.actor} ${entry.source}`.toLowerCase().includes(query);
        }

        const log = item.log;

        return `${log.subject} ${log.body} ${log.template_code} ${log.channel} ${log.status} ${log.failure_reason ?? ''}`.toLowerCase().includes(query);
    });
});

const showNotificationScopeFilters = computed(() => props.notification_logs_enabled
    && (activityScope.value === 'all' || activityScope.value === 'notifications'));

const recentNotificationLogs = computed(() => props.customer_notification_logs.slice(0, 5));

const notificationStatusTone = (status) => {
    if (status === 'sent') {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (status === 'failed') {
        return 'bg-rose-50 text-rose-700';
    }

    return 'bg-amber-50 text-amber-700';
};

const openActivityTab = (scope = 'notifications', notifFilter = 'all') => {
    activeWorkspaceTab.value = 'activity';
    activityScope.value = scope;
    notificationFilter.value = notifFilter;
    customerDrawerOpen.value = false;
};

const inboxTickets = computed(() => props.inbox?.tickets ?? []);

const selectedInboxTicketId = computed(() => props.inbox?.selected_id ?? props.ticket.id);

const inboxUnreadCount = computed(() => inboxTickets.value.filter((ticket) => ticket.has_unread_for_admin).length);

const inboxTicketHref = (ticketId) => route('admin.support.show', {
    supportTicket: ticketId,
    ...(inboxForm.search ? { search: inboxForm.search } : {}),
    ...(inboxForm.status ? { status: inboxForm.status } : {}),
});

const inboxTicketOnline = (ticket) => {
    if (!ticket?.last_message_at) {
        return false;
    }

    return Date.now() - new Date(ticket.last_message_at).getTime() <= 5 * 60 * 1000;
};

const inboxConversationTone = (ticket) => (ticket.id === selectedInboxTicketId.value
    ? 'border-slate-300 bg-slate-50'
    : 'border-transparent hover:border-slate-200 hover:bg-slate-50');

const submitInboxFilters = () => {
    router.get(route('admin.support.show', props.ticket.id), {
        ...(inboxForm.search ? { search: inboxForm.search } : {}),
        ...(inboxForm.status ? { status: inboxForm.status } : {}),
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['ticket', 'inbox'],
    });
};

const amountFieldLabel = computed(() => {
    if (activeOrderAction.value === 'compensation') {
        return t('Compensation amount');
    }

    return t('Refund amount');
});

const compensationTypeOptions = computed(() => selectedOrderActionConfig.value?.compensation_types ?? []);

const openOrderActionModal = (actionName) => {
    activeOrderAction.value = actionName;
    orderActionForm.reset();
    orderActionForm.clearErrors();

    if (actionName === 'compensation') {
        orderActionForm.compensation_type = compensationTypeOptions.value[0] ?? '';
    }
};

const closeOrderActionModal = () => {
    activeOrderAction.value = null;
    orderActionForm.reset();
    orderActionForm.clearErrors();
};

const orderActionRoute = computed(() => {
    if (activeOrderAction.value === 'cancel') {
        return route('admin.support.order.cancel', props.ticket.id);
    }

    if (activeOrderAction.value === 'full_refund') {
        return route('admin.support.order.full-refund', props.ticket.id);
    }

    if (activeOrderAction.value === 'partial_refund') {
        return route('admin.support.order.partial-refund', props.ticket.id);
    }

    if (activeOrderAction.value === 'reverse_refund') {
        return route('admin.support.order.reverse-refund', props.ticket.id);
    }

    if (activeOrderAction.value === 'compensation') {
        return route('admin.support.order.compensation', props.ticket.id);
    }

    return null;
});

const actionVariantClass = (variant) => {
    if (variant === 'danger') {
        return 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100';
    }

    return 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
};

const submitOrderAction = () => {
    if (!orderActionRoute.value) {
        return;
    }

    orderActionForm.post(orderActionRoute.value, {
        preserveScroll: true,
        onSuccess: () => {
            closeOrderActionModal();
        },
    });
};

const onReplyAttachmentChange = (event) => {
    replyForm.attachment = event.target.files?.[0] ?? null;
};

const removeReplyAttachment = () => {
    replyForm.attachment = null;

    if (composerFileInput.value) {
        composerFileInput.value.value = '';
    }
};

const openDocumentPreview = (message) => {
    if (!isPdfAttachment(message)) {
        return;
    }

    activeDocumentPreview.value = message;
};

const closeDocumentPreview = () => {
    activeDocumentPreview.value = null;
};

const openLightbox = (messageId) => {
    const nextIndex = imageMessages.value.findIndex((message) => message.id === messageId);

    if (nextIndex === -1) {
        return;
    }

    lightboxIndex.value = nextIndex;
};

const closeLightbox = () => {
    lightboxIndex.value = null;
};

const showPreviousLightboxImage = () => {
    if (lightboxIndex.value === null || !imageMessages.value.length) {
        return;
    }

    lightboxIndex.value = (lightboxIndex.value - 1 + imageMessages.value.length) % imageMessages.value.length;
};

const showNextLightboxImage = () => {
    if (lightboxIndex.value === null || !imageMessages.value.length) {
        return;
    }

    lightboxIndex.value = (lightboxIndex.value + 1) % imageMessages.value.length;
};

const onLightboxTouchStart = (event) => {
    touchStartX.value = event.changedTouches[0]?.clientX ?? null;
};

const onLightboxTouchEnd = (event) => {
    if (touchStartX.value === null) {
        return;
    }

    const touchEndX = event.changedTouches[0]?.clientX ?? touchStartX.value;
    const deltaX = touchEndX - touchStartX.value;

    if (Math.abs(deltaX) > 50) {
        if (deltaX > 0) {
            showPreviousLightboxImage();
        } else {
            showNextLightboxImage();
        }
    }

    touchStartX.value = null;
};

const resizeComposer = async () => {
    await nextTick();

    if (!composerTextarea.value) {
        return;
    }

    composerTextarea.value.style.height = '0px';
    composerTextarea.value.style.height = `${Math.min(composerTextarea.value.scrollHeight, 180)}px`;
};

const scrollConversationToBottom = async (behavior = 'smooth') => {
    await nextTick();

    if (!messagesViewport.value) {
        return;
    }

    messagesViewport.value.scrollTo({
        top: messagesViewport.value.scrollHeight,
        behavior,
    });
};

watch(() => replyForm.message, () => {
    resizeComposer();
});

watch(() => conversationMessages.value.length, async (nextLength, previousLength) => {
    await scrollConversationToBottom(previousLength === undefined ? 'auto' : 'smooth');

    if (nextLength > previousLength) {
        await resizeComposer();
    }
});

onMounted(async () => {
    await resizeComposer();
    await scrollConversationToBottom('auto');

    const channel = echo?.private(`support.ticket.${props.ticket.id}`);

    if (!channel) {
        return;
    }

    channel.listen('.support.message.broadcasted', async () => {
        await router.reload({
            only: ['ticket', 'inbox'],
            preserveState: true,
            preserveScroll: true,
        });
    });

    channel.listen('.support.ticket.updated', async () => {
        await router.reload({
            only: ['ticket', 'inbox'],
            preserveState: true,
            preserveScroll: true,
        });
    });

    channel.listen('.support.typing.broadcasted', (event) => {
        if (event?.typing?.sender_type !== 'customer') {
            return;
        }

        typingParticipant.value = event.typing.sender?.name || t('Customer');

        if (typingTimeout.value) {
            window.clearTimeout(typingTimeout.value);
        }

        typingTimeout.value = window.setTimeout(() => {
            typingParticipant.value = null;
        }, 3500);
    });
});

onBeforeUnmount(() => {
    if (typingTimeout.value) {
        window.clearTimeout(typingTimeout.value);
    }

    echo?.leave(`support.ticket.${props.ticket.id}`);
});

const submitReply = () => {
    replyForm.post(route('admin.support.reply', props.ticket.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: async () => {
            replyForm.reset();
            removeReplyAttachment();
            await resizeComposer();
        },
    });
};

const submitStatus = () => {
    if (props.resolution_reports_enabled && statusForm.status === 'closed' && !hasClosedResolutionReport.value) {
        activeWorkspaceTab.value = 'resolution_report';
        statusForm.setError('status', t('Complete the resolution report and save it with a closed outcome before closing this ticket.'));

        return;
    }

    statusForm.put(route('admin.support.update-status', props.ticket.id), {
        preserveScroll: true,
    });
};

const submitAssignment = () => {
    assignmentForm.put(route('admin.support.assign', props.ticket.id), {
        preserveScroll: true,
    });
};

const openCustomerDrawer = async () => {
    customerDrawerOpen.value = true;

    if (customerContextLoaded.value || customerContextLoading.value || !canViewUsers.value || !ticketCustomer.value?.id) {
        return;
    }

    customerContextLoading.value = true;
    customerContextError.value = '';

    try {
        const response = await fetch(route('admin.users.show', ticketCustomer.value.id), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Inertia': 'true',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(t('Unable to load customer context.'));
        }

        const payload = await response.json();
        const user = payload?.props?.user ?? null;

        customerContext.value = {
            country: user?.country ?? null,
            recentOrdersCount: Array.isArray(user?.recent_orders) ? user.recent_orders.length : null,
            activeTicketCount: user?.support?.active_ticket_count ?? null,
            lastActivity: user?.recent_activities?.[0]?.created_at ?? user?.last_login_at ?? null,
        };
        customerContextLoaded.value = true;
    } catch (error) {
        customerContextError.value = t('Customer context could not be loaded from the existing profile payload.');
    } finally {
        customerContextLoading.value = false;
    }
};

const closeCustomerDrawer = () => {
    customerDrawerOpen.value = false;
};
</script>

<template>
    <Head :title="ticket.ticket_number" />

    <AdminLayout
        :title="ticket.ticket_number"
        description=""
    >
        <section class="space-y-4">
            <div class="grid gap-4 xl:grid-cols-[260px_minmax(0,1fr)]">
                <aside class="space-y-3 xl:sticky xl:top-4 xl:self-start">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-950">{{ t('Inbox') }}</h3>
                            <span v-if="inboxUnreadCount" class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">
                                {{ inboxUnreadCount }}
                            </span>
                        </div>

                        <form class="mt-3 space-y-2" @submit.prevent="submitInboxFilters">
                            <input
                                v-model="inboxForm.search"
                                type="text"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                :placeholder="t('Search tickets or customer')"
                            >
                            <select
                                v-model="inboxForm.status"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                            >
                                <option value="">{{ t('All statuses') }}</option>
                                <option v-for="status in status_options" :key="status.name" :value="status.name">
                                    {{ t(status.label) }}
                                </option>
                            </select>
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                            >
                                {{ t('Filter') }}
                            </button>
                        </form>
                    </div>

                    <div class="max-h-[calc(100vh-12rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
                        <div class="space-y-1">
                            <Link
                                v-for="inboxTicket in inboxTickets"
                                :key="inboxTicket.id"
                                :href="inboxTicketHref(inboxTicket.id)"
                                class="block rounded-lg border px-3 py-3 transition"
                                :class="inboxConversationTone(inboxTicket)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-950">{{ inboxTicket.user.name || t('Unknown user') }}</p>
                                        <p class="mt-0.5 truncate text-xs text-slate-500">{{ inboxTicket.ticket_number }}</p>
                                    </div>
                                    <span v-if="inboxTicket.has_unread_for_admin" class="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-rose-500" />
                                </div>
                                <p class="mt-2 truncate text-xs text-slate-600">{{ inboxTicket.last_message || t('No messages yet') }}</p>
                                <div class="mt-2 flex items-center justify-between gap-2 text-[11px] text-slate-500">
                                    <span>{{ formatLabel(inboxTicket.status) }}</span>
                                    <span>{{ relativeTime(inboxTicket.last_message_at || inboxTicket.updated_at) }}</span>
                                </div>
                            </Link>

                            <div v-if="inboxTickets.length === 0" class="rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-500">
                                {{ t('No tickets matched the current inbox filters.') }}
                            </div>
                        </div>
                    </div>
                </aside>

                <div class="min-w-0 space-y-4">
                    <div class="overflow-visible rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-4 py-4 sm:px-5">
                            <Link
                                :href="route('admin.support.index')"
                                class="text-sm font-medium text-slate-500 transition hover:text-slate-800"
                            >
                                ← {{ t('Back to support') }}
                            </Link>

                            <div
                                v-if="ticketHealth.show"
                                class="mt-4 rounded-lg border px-4 py-3"
                                :class="ticketHealth.label === t('Critical') ? 'border-rose-200 bg-rose-50' : 'border-amber-200 bg-amber-50'"
                            >
                                <p class="text-sm font-semibold text-slate-950">{{ ticketHealth.label }}</p>
                                <p class="mt-1 text-sm text-slate-700">{{ ticketHealth.description }}</p>
                            </div>

                            <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <h2 class="text-xl font-semibold text-slate-950">{{ ticket.subject }}</h2>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ ticket.ticket_number }} · {{ ticket.user.name || t('Unknown user') }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                        {{ formatLabel(ticket.status) }}
                                    </span>
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                                        {{ formatLabel(ticket.priority) }}
                                    </span>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1" :class="conversationStateTone">
                                        {{ conversationStateLabel }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-3 py-2 sm:px-4">
                            <nav class="flex gap-1 overflow-x-auto">
                                <button
                                    v-for="tab in workspaceTabs"
                                    :key="tab.id"
                                    type="button"
                                    class="shrink-0 rounded-lg px-3 py-2 text-sm font-medium transition"
                                    :class="workspaceTabClass(tab.id)"
                                    @click="changeTab(tab.id)"
                                >
                                    {{ tab.label }}
                                </button>
                            </nav>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                    @click="openCustomerDrawer"
                                >
                                    {{ t('Customer') }}
                                </button>
                                <Link
                                    v-if="orderShowLink"
                                    :href="orderShowLink"
                                    class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                >
                                    {{ t('View order') }}
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_280px]">
                        <div class="min-w-0 space-y-4">
                            <div v-if="activeWorkspaceTab === 'conversation'" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <h3 class="text-sm font-semibold text-slate-950">{{ t('Conversation') }}</h3>
                                        <span class="text-xs text-slate-500">
                                            {{ conversationMessages.length }} {{ t('messages') }} · {{ relativeTime(ticket.last_message_at || ticket.updated_at) }}
                                        </span>
                                    </div>
                                </div>

                                <div v-if="conversationMessages.length === 0" class="px-4 py-6 sm:px-5">
                                    <div class="rounded-lg bg-slate-50 px-4 py-4 text-sm text-slate-600">
                                        {{ t('No customer-facing conversation messages have been recorded for this ticket yet.') }}
                                    </div>
                                </div>

                                <div ref="messagesViewport" class="max-h-[36rem] overflow-y-auto bg-slate-50 px-4 py-4 sm:px-5">
                            <TransitionGroup name="chat-group" tag="div" class="space-y-6">
                                <article
                                    v-for="group in groupedMessages"
                                    :key="group.id"
                                    class="flex transition-all duration-300"
                                    :class="group.sender_type === 'user' ? 'justify-end' : 'justify-start'"
                                >
                                    <div class="flex max-w-[92%] gap-3 sm:max-w-[80%]" :class="groupRowAlignment(group.sender_type)">
                                        <div class="w-11 shrink-0 pt-1">
                                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl text-xs font-semibold uppercase tracking-[0.18em] shadow-sm" :class="avatarTone(group.sender_type)">
                                                {{ initialsFor(group) }}
                                            </div>
                                        </div>

                                        <div class="flex min-w-0 flex-1 flex-col" :class="groupAlignment(group.sender_type)">
                                            <div class="mb-2 flex items-center gap-2 text-xs text-slate-500" :class="group.sender_type === 'user' ? 'flex-row-reverse' : ''">
                                                <span class="font-semibold text-slate-700">{{ actorName(group) }}</span>
                                                <span>{{ relativeTime(group.messages[group.messages.length - 1]?.created_at) }}</span>
                                            </div>

                                            <div class="flex w-full flex-col" :class="group.sender_type === 'user' ? 'items-end gap-2' : 'items-start gap-2'">
                                                <div
                                                    v-for="message in group.messages"
                                                    :key="message.id"
                                                    class="w-full max-w-full rounded-[1.6rem] px-4 py-3 shadow-sm ring-1 transition-all duration-300"
                                                    :class="bubbleTone(group.sender_type, message.is_internal)"
                                                >
                                                    <div class="flex items-start justify-between gap-4">
                                                        <span
                                                            v-if="message.id === latestMessageId"
                                                            class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em]"
                                                            :class="group.sender_type === 'user' ? 'bg-white/10 text-white' : 'bg-slate-100 text-slate-700'"
                                                        >
                                                            {{ latestSenderBadgeLabel(message) }}
                                                        </span>
                                                        <span v-else class="inline-flex h-0" />
                                                        <span class="text-[11px]" :class="metaTone(group.sender_type, message.is_internal)">
                                                            {{ formatDateTime(message.created_at) }}
                                                        </span>
                                                    </div>

                                                    <p
                                                        v-if="message.message"
                                                        class="mt-3 whitespace-pre-line text-sm leading-6"
                                                        :class="bubbleTextTone(group.sender_type, message.is_internal)"
                                                    >
                                                        {{ message.message }}
                                                    </p>

                                                    <button
                                                        v-if="isImageAttachment(message)"
                                                        type="button"
                                                        class="group/image relative mt-3 block w-full overflow-hidden rounded-[1.25rem] border border-white/10 bg-slate-900/10 text-left"
                                                        @click="openLightbox(message.id)"
                                                    >
                                                        <div class="absolute inset-0 animate-pulse bg-slate-200/40 blur-xl transition duration-500 group-hover/image:opacity-0" />
                                                        <img
                                                            :src="attachmentSource(message)"
                                                            :alt="message.attachment_name || t('Attachment preview')"
                                                            class="max-h-80 w-full object-cover transition duration-300 group-hover/image:scale-[1.02]"
                                                            loading="lazy"
                                                        >
                                                        <div class="absolute inset-x-0 bottom-0 flex items-center justify-between bg-gradient-to-t from-slate-950/80 via-slate-950/35 to-transparent px-4 py-3 text-xs text-white">
                                                            <span class="font-medium">{{ message.attachment_name || t('Image attachment') }}</span>
                                                            <span>{{ t('Click to expand') }}</span>
                                                        </div>
                                                    </button>

                                                    <div
                                                        v-else-if="hasDownloadableAttachment(message)"
                                                        class="mt-3 flex items-center gap-3 rounded-[1.25rem] border border-dashed px-4 py-3"
                                                        :class="group.sender_type === 'user' ? 'border-white/15 bg-white/5' : 'border-slate-200 bg-slate-50/80'"
                                                    >
                                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
                                                            :class="group.sender_type === 'user' ? 'bg-white/10 text-white' : 'bg-slate-900 text-white'">
                                                            <span class="text-xs font-semibold uppercase tracking-[0.18em]">{{ attachmentTypeLabel(message) }}</span>
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <p class="truncate text-sm font-semibold" :class="group.sender_type === 'user' ? 'text-white' : 'text-slate-900'">
                                                                {{ message.attachment_name || t('Attachment') }}
                                                            </p>
                                                            <p class="mt-1 text-xs" :class="metaTone(group.sender_type, message.is_internal)">
                                                                {{ message.attachment_mime || t('File') }} · {{ formatAttachmentSize(message.attachment_size) }}
                                                            </p>
                                                        </div>
                                                        <div class="flex flex-col gap-2 sm:flex-row">
                                                            <button
                                                                v-if="isPdfAttachment(message)"
                                                                type="button"
                                                                class="inline-flex items-center justify-center rounded-xl border px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] transition"
                                                                :class="group.sender_type === 'user'
                                                                    ? 'border-white/20 text-white hover:bg-white/10'
                                                                    : 'border-slate-300 text-slate-700 hover:bg-slate-100'"
                                                                @click="openDocumentPreview(message)"
                                                            >
                                                                {{ t('Preview') }}
                                                            </button>
                                                            <a
                                                                :href="attachmentSource(message)"
                                                                :download="message.attachment_name || true"
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                class="inline-flex items-center justify-center rounded-xl border px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] transition"
                                                                :class="group.sender_type === 'user'
                                                                    ? 'border-white/20 text-white hover:bg-white/10'
                                                                    : 'border-slate-300 text-slate-700 hover:bg-slate-100'"
                                                            >
                                                                {{ t('Download') }}
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </TransitionGroup>
                        </div>

                        <div class="border-t border-slate-200 bg-white px-4 py-4 sm:px-5">
                            <div v-if="typingParticipant" class="mb-3 rounded-lg bg-cyan-50 px-3 py-2 text-sm text-cyan-700">
                                {{ t(':name is typing…', { name: typingParticipant }) }}
                            </div>

                            <form class="rounded-xl border border-slate-200 bg-white p-3" @submit.prevent="submitReply">
                                <div v-if="replyForm.attachment" class="mb-3 flex items-center gap-3 rounded-2xl bg-slate-50 px-3 py-3">
                                    <div v-if="composerPreviewUrl" class="h-14 w-14 overflow-hidden rounded-2xl bg-slate-200">
                                        <img :src="composerPreviewUrl" :alt="t('Selected attachment preview')" class="h-full w-full object-cover">
                                    </div>
                                    <div v-else class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-900 text-xs font-semibold uppercase tracking-[0.18em] text-white">
                                        {{ attachmentTypeLabel({ attachment_mime: replyForm.attachment.type, attachment_is_image: replyForm.attachment.type.startsWith('image/') }) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ replyForm.attachment.name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ formatAttachmentSize(replyForm.attachment.size) }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                        @click="removeReplyAttachment"
                                    >
                                        <span class="sr-only">{{ t('Remove attachment') }}</span>
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                            <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="flex items-end gap-3">
                                    <button
                                        type="button"
                                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                                        @click="composerFileInput?.click()"
                                    >
                                        <span class="sr-only">{{ t('Attach file') }}</span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05l-8.49 8.49a5.5 5.5 0 01-7.78-7.78l8.49-8.49a3.5 3.5 0 014.95 4.95l-8.5 8.49a1.5 1.5 0 01-2.12-2.12l7.78-7.78" />
                                        </svg>
                                    </button>

                                    <input
                                        ref="composerFileInput"
                                        type="file"
                                        class="hidden"
                                        accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                        @change="onReplyAttachmentChange"
                                    >

                                    <div class="min-w-0 flex-1 rounded-[1.5rem] border border-slate-200 bg-slate-50/70 px-4 py-3 transition focus-within:border-cyan-500 focus-within:bg-white focus-within:ring-4 focus-within:ring-cyan-100">
                                        <textarea
                                            id="reply_message"
                                            ref="composerTextarea"
                                            v-model="replyForm.message"
                                            rows="1"
                                            class="block min-h-[24px] w-full resize-none border-0 bg-transparent p-0 text-sm leading-6 text-slate-900 outline-none placeholder:text-slate-400"
                                            :placeholder="t('Write a reply like you would in WhatsApp or Intercom')"
                                            @input="resizeComposer"
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-950 text-white shadow-lg shadow-slate-950/20 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="replyForm.processing || (!replyForm.message && !replyForm.attachment)"
                                    >
                                        <span class="sr-only">{{ t('Send reply') }}</span>
                                        <svg v-if="!replyForm.processing" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                            <path d="M3.4 20.4l17.45-7.48a1 1 0 000-1.84L3.4 3.6A.97.97 0 002 4.5l1.75 6.2a1 1 0 00.95.72h7.44a.75.75 0 010 1.5H4.7a1 1 0 00-.95.72L2 19.5a.97.97 0 001.4.9z" />
                                        </svg>
                                        <svg v-else class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.25" stroke-width="3" />
                                            <path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-3 px-1">
                                    <p class="text-xs text-slate-500">{{ replyForm.attachment ? replyFileLabel : t('Images, PDFs, and office files are supported.') }}</p>
                                    <p v-if="replyForm.processing" class="animate-pulse text-xs font-medium text-cyan-700">{{ t('Sending…') }}</p>
                                </div>

                                <p v-if="replyForm.errors.message" class="mt-2 px-1 text-sm text-rose-600">{{ replyForm.errors.message }}</p>
                                <p v-if="replyForm.errors.attachment" class="mt-2 px-1 text-sm text-rose-600">{{ replyForm.errors.attachment }}</p>
                            </form>
                        </div>
                            </div>

                            <div v-else-if="props.resolution_reports_enabled && activeWorkspaceTab === 'resolution_report'" class="space-y-4">
                                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                                        <h3 class="text-sm font-semibold text-slate-950">{{ t('Resolution Report') }}</h3>
                                        <span class="text-xs font-medium" :class="resolutionReport ? 'text-emerald-700' : 'text-slate-500'">
                                            {{ resolutionReport ? t('Recorded') : t('Pending') }}
                                        </span>
                                    </div>
                                    <div class="mt-4">
                                        <ResolutionReportView :report="resolutionReport" :ticket-id="ticket.id" />
                                    </div>
                                </div>

                                <ResolutionReportForm
                                    :ticket-id="ticket.id"
                                    :report="resolutionReport"
                                    :resolution-type-options="resolution_type_options"
                                    :resolution-status-options="resolution_status_options"
                                />
                            </div>

                            <div v-else-if="activeWorkspaceTab === 'activity'" class="space-y-4">
                                <SystemTimeline :events="system_timeline" title="System Timeline" />

                                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-950">{{ t('Activity') }}</h3>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{ t('Timeline events and notification delivery logs in one place.') }}
                                        </p>
                                    </div>
                                    <label class="block w-full lg:max-w-sm">
                                        <span class="sr-only">{{ t('Search activity') }}</span>
                                        <input
                                            v-model="activityQuery"
                                            type="text"
                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                            :placeholder="t('Search events, notifications, or actors')"
                                        >
                                    </label>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                                        :class="activityScope === 'all' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                        @click="activityScope = 'all'"
                                    >
                                        {{ t('All') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                                        :class="activityScope === 'events' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                        @click="activityScope = 'events'"
                                    >
                                        {{ t('Events') }}
                                    </button>
                                    <button
                                        v-if="notification_logs_enabled"
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                                        :class="activityScope === 'notifications' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                        @click="activityScope = 'notifications'"
                                    >
                                        {{ t('Notifications') }}
                                    </button>
                                </div>

                                <div v-if="showNotificationScopeFilters" class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                                        :class="notificationFilter === 'all' ? 'bg-cyan-950 text-white' : 'bg-cyan-50 text-cyan-700 hover:bg-cyan-100'"
                                        @click="notificationFilter = 'all'"
                                    >
                                        {{ t('All notifications') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                                        :class="notificationFilter === 'ticket' ? 'bg-cyan-950 text-white' : 'bg-cyan-50 text-cyan-700 hover:bg-cyan-100'"
                                        @click="notificationFilter = 'ticket'"
                                    >
                                        {{ t('This ticket') }}
                                    </button>
                                    <button
                                        v-if="ticket.order"
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                                        :class="notificationFilter === 'order' ? 'bg-cyan-950 text-white' : 'bg-cyan-50 text-cyan-700 hover:bg-cyan-100'"
                                        @click="notificationFilter = 'order'"
                                    >
                                        {{ t('This order') }}
                                    </button>
                                </div>

                                <div v-if="filteredActivityItems.length === 0" class="mt-4 rounded-lg bg-slate-50 px-4 py-4 text-sm text-slate-600">
                                    {{ t('No activity matched the current filters.') }}
                                </div>

                                <div v-else class="mt-6 space-y-0">
                                    <div
                                        v-for="(item, index) in filteredActivityItems"
                                        :key="item.id"
                                        class="relative flex gap-4 pb-6"
                                    >
                                        <div class="relative flex w-10 shrink-0 justify-center">
                                            <span
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-[10px] font-semibold uppercase"
                                                :class="item.kind === 'notification' ? 'bg-cyan-100 text-cyan-800' : 'bg-slate-100 text-slate-700'"
                                            >
                                                {{ item.kind === 'notification' ? 'NT' : t(formatLabel(item.entry.source)).slice(0, 2) }}
                                            </span>
                                            <span v-if="index !== filteredActivityItems.length - 1" class="absolute top-10 h-[calc(100%-0.5rem)] w-px bg-slate-200" />
                                        </div>

                                        <article
                                            v-if="item.kind === 'event'"
                                            class="min-w-0 flex-1 rounded-lg border border-slate-200 px-4 py-3"
                                        >
                                            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <h4 class="text-sm font-medium text-slate-950">{{ t(item.entry.event) }}</h4>
                                                    <p class="text-xs text-slate-500">{{ item.entry.actor || t('System') }}</p>
                                                </div>
                                                <p class="text-xs text-slate-500">{{ formatDateTime(item.entry.created_at) }}</p>
                                            </div>
                                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ item.entry.description }}</p>
                                            <p v-if="item.entry.amount" class="mt-2 text-xs font-medium text-slate-700">
                                                {{ formatMoney(item.entry.amount, item.entry.currency) }}
                                            </p>
                                        </article>

                                        <article
                                            v-else
                                            class="min-w-0 flex-1 rounded-lg border border-slate-200 px-4 py-3"
                                        >
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="notificationStatusTone(item.log.status)">
                                                            {{ formatLabel(item.log.status) }}
                                                        </span>
                                                        <span class="text-xs font-medium text-slate-700">{{ formatLabel(item.log.channel) }}</span>
                                                        <span v-if="item.log.is_ticket_related" class="rounded-full bg-cyan-50 px-2 py-0.5 text-[11px] font-medium text-cyan-700">
                                                            {{ t('Ticket') }}
                                                        </span>
                                                        <span v-if="item.log.is_order_related" class="rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700">
                                                            {{ t('Order') }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-2 text-sm font-medium text-slate-950">{{ item.log.subject || item.log.template_code || t('Notification') }}</p>
                                                    <p class="mt-1 text-xs text-slate-500">{{ item.log.template_code }} · {{ formatLabel(item.log.notification_type || item.log.related_type || t('general')) }}</p>
                                                </div>
                                                <p class="shrink-0 text-xs text-slate-500">
                                                    {{ formatDateTime(item.log.sent_at || item.log.failed_at || item.log.created_at) }}
                                                </p>
                                            </div>
                                            <p v-if="item.log.body" class="mt-2 text-sm leading-6 text-slate-600">{{ item.log.body }}</p>
                                            <p v-if="item.log.failure_reason" class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">
                                                {{ t('Failure reason') }}: {{ item.log.failure_reason }}
                                            </p>
                                            <p v-if="item.log.retry_count" class="mt-2 text-xs text-slate-500">
                                                {{ t('Retries') }}: {{ item.log.retry_count }}
                                            </p>
                                        </article>
                                    </div>
                                </div>

                                <div v-if="canViewNotificationLogs && notification_logs_enabled" class="mt-4 border-t border-slate-100 pt-4">
                                    <Link
                                        :href="route('admin.notifications.index')"
                                        class="text-sm font-medium text-slate-600 transition hover:text-slate-900"
                                    >
                                        {{ t('Open full notifications console') }} →
                                    </Link>
                                </div>
                            </div>
                            </div>

                            <div v-else-if="activeWorkspaceTab === 'details'" class="space-y-4">
                                <OrderTicketPanel
                                    v-if="ticket.order_ticket"
                                    :ticket="ticket.order_ticket"
                                    :currency="ticket.order?.currency"
                                    :booked-by-clickable="Boolean(ticket.user?.id)"
                                    :show-booking-actions="false"
                                    @booked-by-click="openCustomerDrawer"
                                />

                                <div
                                    v-else-if="!ticket.order"
                                    class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm"
                                >
                                    {{ t('This support ticket is not linked to an order, so no booking ticket view is available.') }}
                                </div>

                                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <h3 class="text-sm font-semibold text-slate-950">{{ t('Support case') }}</h3>
                                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">{{ t('Ticket number') }}</dt>
                                            <dd class="mt-1 text-sm text-slate-900">{{ ticket.ticket_number }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">{{ t('Category') }}</dt>
                                            <dd class="mt-1 text-sm text-slate-900">{{ formatLabel(ticket.category) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">{{ t('Assigned to') }}</dt>
                                            <dd class="mt-1 text-sm text-slate-900">{{ ticket.assignee?.name || t('Unassigned') }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">{{ t('Priority') }}</dt>
                                            <dd class="mt-1 text-sm text-slate-900">{{ formatLabel(ticket.priority) }}</dd>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <dt class="text-xs font-medium text-slate-500">{{ t('Subject') }}</dt>
                                            <dd class="mt-1 text-sm text-slate-900">{{ ticket.subject }}</dd>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <dt class="text-xs font-medium text-slate-500">{{ t('Description') }}</dt>
                                            <dd class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-900">{{ ticket.description }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">{{ t('Created at') }}</dt>
                                            <dd class="mt-1 text-sm text-slate-900">{{ formatDateTime(ticket.created_at) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">{{ t('Updated at') }}</dt>
                                            <dd class="mt-1 text-sm text-slate-900">{{ formatDateTime(ticket.updated_at) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">{{ t('First response due') }}</dt>
                                            <dd class="mt-1 text-sm text-slate-900">{{ formatDateTime(ticket.first_response_due_at) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">{{ t('Resolution due') }}</dt>
                                            <dd class="mt-1 text-sm text-slate-900">{{ formatDateTime(ticket.resolution_due_at) }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <aside class="space-y-3 xl:sticky xl:top-4 xl:self-start">
                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <h3 class="text-sm font-semibold text-slate-950">{{ t('Workflow') }}</h3>

                                <form class="mt-3 space-y-3" @submit.prevent="submitStatus">
                                    <label class="block space-y-1">
                                        <span class="text-xs font-medium text-slate-600">{{ t('Status') }}</span>
                                        <select
                                            id="ticket_status"
                                            v-model="statusForm.status"
                                            class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"
                                        >
                                            <option v-for="status in status_options" :key="status.name" :value="status.name">
                                                {{ t(status.label) }}
                                            </option>
                                        </select>
                                    </label>
                                    <button
                                        type="submit"
                                        class="inline-flex w-full items-center justify-center rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                                        :disabled="statusForm.processing"
                                    >
                                        {{ t('Update status') }}
                                    </button>
                                    <p v-if="statusForm.errors.status" class="text-sm text-rose-600">{{ statusForm.errors.status }}</p>
                                    <p v-if="props.resolution_reports_enabled && !hasClosedResolutionReport" class="text-xs text-slate-500">
                                        {{ t('Closing requires a resolution report with a closed outcome.') }}
                                    </p>
                                </form>

                                <form class="mt-4 space-y-3 border-t border-slate-100 pt-4" @submit.prevent="submitAssignment">
                                    <label class="block space-y-1">
                                        <span class="text-xs font-medium text-slate-600">{{ t('Assigned agent') }}</span>
                                        <select
                                            id="assigned_agent_id"
                                            v-model="assignmentForm.assigned_agent_id"
                                            class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"
                                        >
                                            <option value="">{{ t('Unassigned') }}</option>
                                            <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                                                {{ agent.name }}
                                            </option>
                                        </select>
                                    </label>
                                    <button
                                        type="submit"
                                        class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                                        :disabled="assignmentForm.processing"
                                    >
                                        {{ t('Save assignment') }}
                                    </button>
                                    <p v-if="assignmentForm.errors.assigned_agent_id" class="text-sm text-rose-600">{{ assignmentForm.errors.assigned_agent_id }}</p>
                                </form>
                            </div>

                            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 text-left"
                                    @click="internalNotesExpanded = !internalNotesExpanded"
                                >
                                    <div>
                                        <h3 class="text-sm font-semibold text-amber-950">{{ t('Internal Notes') }}</h3>
                                        <p class="mt-1 text-xs text-amber-800">{{ t('Visible to agents only') }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                        {{ internalMessages.length }}
                                    </span>
                                </button>

                                <div v-if="internalNotesExpanded" class="mt-3 space-y-2">
                                    <div v-if="internalMessages.length === 0" class="rounded-lg bg-white/70 px-3 py-3 text-sm text-amber-900">
                                        {{ t('No internal notes have been recorded for this ticket yet.') }}
                                    </div>

                                    <div v-else class="max-h-56 space-y-2 overflow-y-auto pr-1">
                                        <article
                                            v-for="message in internalMessages"
                                            :key="message.id"
                                            class="rounded-lg border border-amber-200 bg-white px-3 py-2.5"
                                        >
                                            <div class="flex items-start justify-between gap-2">
                                                <p class="text-xs font-medium text-amber-950">{{ message.user?.name || t('System') }}</p>
                                                <p class="text-[11px] text-amber-700">{{ formatDateTime(message.created_at) }}</p>
                                            </div>
                                            <p class="mt-1.5 whitespace-pre-line text-sm leading-5 text-amber-900">{{ message.message || t('No content') }}</p>
                                        </article>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="w-full rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                                @click="openCustomerDrawer"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-semibold text-slate-950">{{ t('Customer') }}</h3>
                                        <p class="mt-2 text-sm font-medium text-slate-900">{{ ticket.user.name || t('Unknown user') }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ ticket.user.email || t('No email') }}</p>
                                        <p v-if="ticket.user.phone" class="mt-1 text-xs text-slate-500" dir="ltr">{{ ticket.user.phone }}</p>
                                    </div>
                                    <span class="shrink-0 text-xs font-medium text-slate-500">{{ t('Open') }}</span>
                                </div>
                            </button>

                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <h3 class="text-sm font-semibold text-slate-950">{{ t('Order') }}</h3>

                                <div v-if="!ticket.order" class="mt-3 rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-600">
                                    {{ t('This ticket is not linked to an order.') }}
                                </div>

                                <template v-else>
                                    <dl class="mt-3 space-y-3">
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">{{ t('Reference') }}</dt>
                                            <dd class="mt-0.5 text-sm text-slate-900">{{ ticket.order.reference }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-slate-500">{{ t('Status') }}</dt>
                                            <dd class="mt-0.5 text-sm text-slate-900">
                                                {{ formatLabel(ticket.order.status) }} · {{ formatLabel(ticket.order.payment_status) }}
                                            </dd>
                                        </div>
                                        <div v-if="orderSnapshot">
                                            <dt class="text-xs font-medium text-slate-500">{{ t('Total') }}</dt>
                                            <dd class="mt-0.5 text-sm font-medium text-slate-900">
                                                {{ formatMoney(orderSnapshot.order_total, orderSnapshot.currency) }}
                                            </dd>
                                        </div>
                                    </dl>

                                    <div v-if="canManageOrderActions && availableOrderActions.length" class="mt-4 flex flex-wrap gap-2">
                                        <button
                                            v-for="action in availableOrderActions"
                                            :key="action.name"
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg border px-3 py-2 text-xs font-medium transition"
                                            :class="actionVariantClass(action.variant)"
                                            @click="openOrderActionModal(action.name)"
                                        >
                                            {{ t(action.label) }}
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </section>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="customerDrawerOpen"
                    class="fixed inset-0 z-50 bg-slate-950/30 backdrop-blur-sm"
                    @click="closeCustomerDrawer"
                />
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <aside
                    v-if="customerDrawerOpen"
                    class="fixed inset-y-0 right-0 z-[60] flex w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-[0_20px_80px_-20px_rgba(15,23,42,0.45)]"
                >
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">{{ t('Customer Drawer') }}</p>
                                <h3 class="mt-2 text-2xl font-semibold text-slate-950">{{ ticket.user.name || t('Unknown user') }}</h3>
                                <p class="mt-2 text-sm text-slate-600">{{ ticket.user.email || t('No email') }}</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-white hover:text-slate-900"
                                @click="closeCustomerDrawer"
                            >
                                <span class="sr-only">{{ t('Close drawer') }}</span>
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                                    <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto px-6 py-6">
                        <div class="space-y-6">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                                <h4 class="text-lg font-semibold text-slate-950">{{ t('Customer profile') }}</h4>
                                <dl class="mt-5 grid gap-4">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Name') }}</dt>
                                        <dd class="mt-2 text-sm text-slate-900">{{ ticket.user.name || t('Unknown user') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Email') }}</dt>
                                        <dd class="mt-2 text-sm text-slate-900">{{ ticket.user.email || t('No email') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Phone') }}</dt>
                                        <dd class="mt-2 text-sm text-slate-900">{{ ticket.user.phone || t('Not provided') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Country') }}</dt>
                                        <dd class="mt-2 text-sm text-slate-900">
                                            {{ customerContext.country || ticket.user.country || (customerContextLoading ? t('Loading...') : t('Not available')) }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-xl border border-slate-200 p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-lg font-semibold text-slate-950">{{ t('Quick stats') }}</h4>
                                    <span v-if="customerContextLoading" class="text-xs font-medium uppercase tracking-[0.16em] text-slate-400">{{ t('Loading') }}</span>
                                </div>
                                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                    <div class="rounded-lg bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Recent orders') }}</p>
                                        <p class="mt-2 text-xl font-semibold text-slate-950">{{ customerContext.recentOrdersCount ?? '-' }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Active tickets') }}</p>
                                        <p class="mt-2 text-xl font-semibold text-slate-950">{{ customerContext.activeTicketCount ?? '-' }}</p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-4 sm:col-span-2">
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Last activity') }}</p>
                                        <p class="mt-2 text-sm font-medium text-slate-950">{{ formatDateTime(customerContext.lastActivity || ticket.updated_at) }}</p>
                                    </div>
                                </div>
                                <p v-if="customerContextError" class="mt-4 text-sm text-rose-600">{{ customerContextError }}</p>
                            </div>

                            <div v-if="notification_logs_enabled" class="rounded-xl border border-slate-200 p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-lg font-semibold text-slate-950">{{ t('Notification deliveries') }}</h4>
                                    <button
                                        v-if="customer_notification_logs.length > 5"
                                        type="button"
                                        class="text-xs font-medium text-slate-500 transition hover:text-slate-800"
                                        @click="openActivityTab('notifications')"
                                    >
                                        {{ t('View all') }}
                                    </button>
                                </div>
                                <p class="mt-2 text-sm text-slate-600">
                                    {{ t('Use these logs when the customer says they did not receive an email, SMS, or push notification.') }}
                                </p>

                                <div v-if="recentNotificationLogs.length === 0" class="mt-4 rounded-lg bg-slate-50 px-4 py-4 text-sm text-slate-600">
                                    {{ t('No notification delivery logs were found for this customer yet.') }}
                                </div>

                                <div v-else class="mt-4 space-y-3">
                                    <article
                                        v-for="log in recentNotificationLogs"
                                        :key="`drawer-log-${log.id}`"
                                        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-medium" :class="notificationStatusTone(log.status)">
                                                        {{ formatLabel(log.status) }}
                                                    </span>
                                                    <span class="text-[11px] font-medium text-slate-600">{{ formatLabel(log.channel) }}</span>
                                                </div>
                                                <p class="mt-1 truncate text-sm font-medium text-slate-900">{{ log.subject || log.template_code }}</p>
                                            </div>
                                            <span class="shrink-0 text-[11px] text-slate-500">{{ formatDateTime(log.sent_at || log.failed_at || log.created_at) }}</span>
                                        </div>
                                        <p v-if="log.failure_reason" class="mt-2 text-xs text-rose-700">{{ log.failure_reason }}</p>
                                    </article>
                                </div>

                                <button
                                    type="button"
                                    class="mt-4 inline-flex items-center text-sm font-medium text-slate-700 transition hover:text-slate-950"
                                    @click="openActivityTab('notifications')"
                                >
                                    {{ t('Open activity tab') }} →
                                </button>
                            </div>

                            <div class="rounded-xl border border-slate-200 p-5">
                                <h4 class="text-lg font-semibold text-slate-950">{{ t('Actions') }}</h4>
                                <div class="mt-4 grid gap-3">
                                    <Link
                                        v-if="canViewOrders"
                                        :href="customerOrdersLink"
                                        class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 transition hover:bg-slate-50"
                                    >
                                        <span>
                                            <span class="block text-sm font-semibold text-slate-950">{{ t('View all orders') }}</span>
                                            <span class="mt-1 block text-xs text-slate-500">{{ t('Jump to the customer workspace and recent order list.') }}</span>
                                        </span>
                                        <span class="text-sm font-medium text-slate-600">{{ t('Open') }}</span>
                                    </Link>

                                    <Link
                                        v-if="canViewSupport"
                                        :href="relatedTicketsLink"
                                        class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 transition hover:bg-slate-50"
                                    >
                                        <span>
                                            <span class="block text-sm font-semibold text-slate-950">{{ t('View support tickets') }}</span>
                                            <span class="mt-1 block text-xs text-slate-500">{{ t('Open support filtered by the customer.') }}</span>
                                        </span>
                                        <span class="text-sm font-medium text-slate-600">{{ t('Open') }}</span>
                                    </Link>

                                    <Link
                                        v-if="canViewSupport"
                                        :href="supportCreateLink"
                                        class="flex items-center justify-between rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 transition hover:bg-cyan-100"
                                    >
                                        <span>
                                            <span class="block text-sm font-semibold text-cyan-800">{{ t('Create Support Ticket') }}</span>
                                            <span class="mt-1 block text-xs text-cyan-700">{{ t('Start a new ticket for this customer.') }}</span>
                                        </span>
                                        <span class="text-sm font-medium text-cyan-800">{{ t('Open') }}</span>
                                    </Link>

                                    <Link
                                        v-if="customerProfileLink"
                                        :href="customerProfileLink"
                                        class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 transition hover:bg-slate-50"
                                    >
                                        <span>
                                            <span class="block text-sm font-semibold text-slate-950">{{ t('Open customer profile') }}</span>
                                            <span class="mt-1 block text-xs text-slate-500">{{ t('View the unified customer CRM page.') }}</span>
                                        </span>
                                        <span class="text-sm font-medium text-slate-600">{{ t('Open') }}</span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="selectedOrderActionConfig"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm"
                    @click.self="closeOrderActionModal"
                >
                    <div class="w-full max-w-xl rounded-[2rem] border border-slate-200 bg-white p-6 shadow-2xl">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">{{ t('Action Preview') }}</p>
                                <h3 class="mt-2 text-2xl font-semibold text-slate-950">{{ t(selectedOrderActionConfig.label) }}</h3>
                                <p class="mt-3 text-sm leading-6 text-slate-600">
                                    {{ t('You are about to run a manual order action from the support workspace. Review the order, add an internal reason, and continue only if the outcome is correct.') }}
                                </p>
                            </div>

                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                                @click="closeOrderActionModal"
                            >
                                <span class="sr-only">{{ t('Close') }}</span>
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                                    <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-6 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-700">
                            <p><span class="font-semibold text-slate-950">{{ t('Order') }}:</span> {{ ticket.order.reference }}</p>
                            <p class="mt-2"><span class="font-semibold text-slate-950">{{ t('Order status') }}:</span> {{ formatLabel(ticket.order.status) }}</p>
                            <p class="mt-2"><span class="font-semibold text-slate-950">{{ t('Payment status') }}:</span> {{ formatLabel(ticket.order.payment_status) }}</p>
                            <p class="mt-2"><span class="font-semibold text-slate-950">{{ t('Total amount') }}:</span> {{ formatMoney(ticket.order.total_amount, ticket.order.currency) }}</p>
                            <p v-if="selectedOrderActionConfig?.available_amount" class="mt-2"><span class="font-semibold text-slate-950">{{ t('Available amount') }}:</span> {{ formatMoney(selectedOrderActionConfig.available_amount, ticket.order.currency) }}</p>
                        </div>

                        <form class="mt-6 space-y-5" @submit.prevent="submitOrderAction">
                            <div v-if="selectedOrderActionConfig.requires_amount">
                                <label for="order_action_amount" class="text-sm font-medium text-slate-700">{{ amountFieldLabel }}</label>
                                <input
                                    id="order_action_amount"
                                    v-model="orderActionForm.amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                                >
                                <p v-if="orderActionForm.errors.amount" class="mt-2 text-sm text-rose-600">{{ orderActionForm.errors.amount }}</p>
                            </div>

                            <div v-if="selectedOrderActionConfig.requires_compensation_type">
                                <label for="order_action_compensation_type" class="text-sm font-medium text-slate-700">{{ t('Compensation type') }}</label>
                                <select
                                    id="order_action_compensation_type"
                                    v-model="orderActionForm.compensation_type"
                                    class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                                >
                                    <option v-for="type in compensationTypeOptions" :key="type" :value="type">
                                        {{ formatLabel(type) }}
                                    </option>
                                </select>
                                <p v-if="orderActionForm.errors.compensation_type" class="mt-2 text-sm text-rose-600">{{ orderActionForm.errors.compensation_type }}</p>
                            </div>

                            <div>
                                <label for="order_action_reason" class="text-sm font-medium text-slate-700">{{ t('Reason') }}</label>
                                <textarea
                                    id="order_action_reason"
                                    v-model="orderActionForm.reason"
                                    rows="4"
                                    class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm leading-6 text-slate-900 outline-none transition focus:border-cyan-600"
                                    :placeholder="t('Add an internal reason that will be stored in the support timeline.')"
                                />
                                <p v-if="orderActionForm.errors.reason" class="mt-2 text-sm text-rose-600">{{ orderActionForm.errors.reason }}</p>
                            </div>

                            <div v-if="selectedOrderActionConfig.requires_internal_note">
                                <label for="order_action_internal_note" class="text-sm font-medium text-slate-700">{{ t('Internal note') }}</label>
                                <textarea
                                    id="order_action_internal_note"
                                    v-model="orderActionForm.internal_note"
                                    rows="3"
                                    class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm leading-6 text-slate-900 outline-none transition focus:border-cyan-600"
                                    :placeholder="t('Add an internal note for the finance and support audit trail.')"
                                />
                                <p v-if="orderActionForm.errors.internal_note" class="mt-2 text-sm text-rose-600">{{ orderActionForm.errors.internal_note }}</p>
                            </div>

                            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-900">
                                {{ t('This action changes the linked order and writes an internal support audit entry.') }}
                            </div>

                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                    @click="closeOrderActionModal"
                                >
                                    {{ t('Cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                                    :disabled="orderActionForm.processing"
                                >
                                    {{ t('Confirm action') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="activeLightboxMessage"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/90 px-4 py-6 backdrop-blur-sm"
                    @click.self="closeLightbox"
                    @touchstart="onLightboxTouchStart"
                    @touchend="onLightboxTouchEnd"
                >
                    <button
                        type="button"
                        class="absolute right-5 top-5 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                        @click="closeLightbox"
                    >
                        <span class="sr-only">{{ t('Close image viewer') }}</span>
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                            <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <button
                        v-if="imageMessages.length > 1"
                        type="button"
                        class="absolute left-5 top-1/2 inline-flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                        @click="showPreviousLightboxImage"
                    >
                        <span class="sr-only">{{ t('Previous image') }}</span>
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M11.78 15.78a.75.75 0 01-1.06 0L5.47 10.53a.75.75 0 010-1.06l5.25-5.25a.75.75 0 111.06 1.06L7.06 10l4.72 4.72a.75.75 0 010 1.06z" clip-rule="evenodd" /></svg>
                    </button>

                    <div class="mx-auto flex max-h-full w-full max-w-5xl flex-col items-center gap-4">
                        <div class="overflow-hidden rounded-[2rem] border border-white/10 bg-black/20 shadow-2xl">
                            <img
                                :src="attachmentSource(activeLightboxMessage)"
                                :alt="activeLightboxMessage.attachment_name || t('Expanded attachment')"
                                class="max-h-[75vh] w-full object-contain"
                            >
                        </div>
                        <div class="rounded-2xl bg-white/10 px-4 py-3 text-center text-white backdrop-blur">
                            <p class="text-sm font-medium">{{ activeLightboxMessage.attachment_name || t('Image attachment') }}</p>
                            <p class="mt-1 text-xs text-slate-200">{{ lightboxIndex + 1 }} / {{ imageMessages.length }} · {{ t('Swipe or use arrows to navigate') }}</p>
                        </div>
                    </div>

                    <button
                        v-if="imageMessages.length > 1"
                        type="button"
                        class="absolute right-5 top-1/2 inline-flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                        @click="showNextLightboxImage"
                    >
                        <span class="sr-only">{{ t('Next image') }}</span>
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M8.22 4.22a.75.75 0 011.06 0l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 11-1.06-1.06L12.94 10 8.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" /></svg>
                    </button>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="activeDocumentPreview"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/90 px-4 py-6 backdrop-blur-sm"
                    @click.self="closeDocumentPreview"
                >
                    <div class="flex h-[88vh] w-full max-w-5xl flex-col overflow-hidden rounded-[2rem] border border-white/10 bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-950">{{ activeDocumentPreview.attachment_name || t('PDF preview') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ activeDocumentPreview.attachment_mime || t('application/pdf') }} · {{ formatAttachmentSize(activeDocumentPreview.attachment_size) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a
                                    :href="attachmentSource(activeDocumentPreview)"
                                    :download="activeDocumentPreview.attachment_name || true"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 transition hover:bg-slate-50"
                                >
                                    {{ t('Download') }}
                                </a>
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                                    @click="closeDocumentPreview"
                                >
                                    <span class="sr-only">{{ t('Close PDF preview') }}</span>
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                                        <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <iframe
                            :src="attachmentSource(activeDocumentPreview)"
                            class="min-h-0 w-full flex-1 bg-slate-100"
                            :title="t('PDF preview')"
                        />
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
.chat-group-enter-active,
.chat-group-leave-active {
    transition: all 0.28s ease;
}

.chat-group-enter-from,
.chat-group-leave-to {
    opacity: 0;
    transform: translateY(14px);
}

.chat-group-move {
    transition: transform 0.28s ease;
}
</style>