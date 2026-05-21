<?php

namespace App\Modules\Api\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Modules\Admin\Support\Services\SupportService;
use App\Modules\Api\Support\Http\Requests\CreateSupportTicketRequest;
use App\Modules\Api\Support\Http\Requests\StoreSupportReplyRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SupportApiController extends Controller
{
    public function __construct(
        private readonly SupportService $supportService,
    ) {
    }

    /**
     * Create a support ticket for the authenticated customer.
     */
    public function store(CreateSupportTicketRequest $request): JsonResponse
    {
        return $this->sendMessage($request);
    }

    /**
     * Fetch the latest matching open support conversation for the authenticated customer.
     */
    public function currentConversation(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isCustomerAccount(), 403);

        $validated = $request->validate([
            'order_id' => ['nullable', 'integer', 'exists:orders,id', 'required_without:subject'],
            'subject' => ['nullable', 'string', 'max:255', 'required_without:order_id'],
        ]);

        $ticket = $this->supportService->getCurrentConversation(
            $request->user(),
            $validated['subject'] ?? null,
            isset($validated['order_id']) ? (int) $validated['order_id'] : null,
        );

        return ApiResponse::success(
            ['ticket' => $ticket ? $this->detailPayload($ticket) : null],
            'Current support conversation fetched successfully.',
        );
    }

    /**
     * Send a support message for the authenticated customer.
     */
    public function sendMessage(CreateSupportTicketRequest $request): JsonResponse
    {
        $result = $this->supportService->sendCustomerMessage([
            'user_id' => $request->user()->id,
            'order_id' => $request->integer('order_id') ?: null,
            'category' => $request->string('category')->value(),
            'priority' => $request->string('priority')->value(),
            'status' => 'open',
            'assigned_to' => null,
            'subject' => $request->string('subject')->value(),
        ], $request->string('message')->value(), $request->user()->id, $this->storeAttachment($request->file('attachment')));

        $ticket = $this->supportService->getTicketDetails($request->user(), $result['ticket']->id);

        return ApiResponse::success(
            ['ticket' => $this->detailPayload($ticket)],
            $result['created'] ? 'Support ticket created successfully.' : 'Support message sent successfully.',
            [],
            $result['created'] ? 201 : 200,
        );
    }

    /**
     * List the authenticated customer's support tickets.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isCustomerAccount(), 403);

        $tickets = $this->supportService->getUserTickets($request->user());

        return ApiResponse::success(
            [
                'tickets' => collect($tickets->items())
                    ->map(fn (SupportTicket $ticket): array => $this->summaryPayload($ticket))
                    ->values()
                    ->all(),
            ],
            'Support tickets fetched successfully.',
            [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        );
    }

    /**
     * Display the authenticated customer's support ticket.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->isCustomerAccount(), 403);

        $ticket = $this->supportService->getTicketDetails($request->user(), $id);

        return ApiResponse::success(
            ['ticket' => $this->detailPayload($ticket)],
            'Support ticket fetched successfully.',
        );
    }

    /**
     * Add a reply to the authenticated customer's support ticket.
     */
    public function reply(StoreSupportReplyRequest $request, int $id): JsonResponse
    {
        $ticket = $this->supportService->getTicketDetails($request->user(), $id);

        $this->supportService->addCustomerMessage(
            $ticket,
            $request->string('message')->value(),
            $request->user()->id,
            $this->storeAttachment($request->file('attachment')),
        );

        $ticket = $this->supportService->getTicketDetails($request->user(), $id);

        return ApiResponse::success(
            ['ticket' => $this->detailPayload($ticket)],
            'Support reply added successfully.',
        );
    }

    /**
     * Build a support ticket summary payload for API listing.
     *
     * @return array<string, mixed>
     */
    private function summaryPayload(SupportTicket $ticket): array
    {
        $lastMessage = $ticket->relationLoaded('latestMessage')
            ? $ticket->latestMessage
            : $ticket->messages->last();

        $lastSenderType = $lastMessage?->user?->isAdminAccount() ? 'agent' : ($lastMessage ? 'user' : null);

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'subject' => $ticket->subject,
            'last_message' => $lastMessage?->message,
            'last_message_at' => $lastMessage?->created_at?->toDateTimeString(),
            'last_sender_type' => $lastSenderType,
            'has_unread_for_admin' => $lastSenderType === 'user',
            'has_unread_for_customer' => $lastSenderType === 'agent',
            'conversation_state' => $this->conversationState($lastSenderType),
            'sla_status' => $this->supportService->slaStatusFor($ticket),
            'sla_risk' => $this->supportService->slaRiskFor($ticket),
            'agent_workload_percentage' => $this->supportService->agentWorkloadPercentageFor($ticket->assignee),
            'created_at' => $ticket->created_at?->toDateTimeString(),
            'updated_at' => $ticket->updated_at?->toDateTimeString(),
            'assignee' => $ticket->assignee
                ? [
                    'id' => $ticket->assignee->id,
                    'name' => $ticket->assignee->full_name ?: $ticket->assignee->name,
                    'email' => $ticket->assignee->email,
                ]
                : null,
            'order' => $ticket->order
                ? [
                    'id' => $ticket->order->id,
                    'reference' => $ticket->order->booking_reference ?: $ticket->order->external_booking_id ?: 'Order #'.$ticket->order->id,
                    'status' => $ticket->order->status,
                ]
                : null,
        ];
    }

    /**
     * Build a support ticket detail payload for API responses.
     *
     * @return array<string, mixed>
     */
    private function detailPayload(SupportTicket $ticket): array
    {
        return [
            ...$this->summaryPayload($ticket),
            'description' => $ticket->description,
            'first_response_due_at' => $ticket->first_response_due_at?->toDateTimeString(),
            'resolution_due_at' => $ticket->resolution_due_at?->toDateTimeString(),
            'resolved_at' => $ticket->resolved_at?->toDateTimeString(),
            'closed_at' => $ticket->closed_at?->toDateTimeString(),
            'user' => [
                'id' => $ticket->user?->id,
                'name' => $ticket->user?->full_name ?: $ticket->user?->name,
                'email' => $ticket->user?->email,
                'phone' => $ticket->user?->phone,
                'country' => $ticket->user?->country,
                'created_at' => $ticket->user?->created_at?->toDateTimeString(),
            ],
            'messages' => $ticket->messages
                ->map(fn (SupportMessage $message): array => [
                    'id' => $message->id,
                    'message' => $message->message,
                    'is_internal' => $message->is_internal,
                    'sender_type' => $message->user?->isAdminAccount() ? 'agent' : 'user',
                    'attachment_path' => $message->attachment_path,
                    'attachment_name' => $message->attachment_name,
                    'attachment_mime' => $message->attachment_mime,
                    'attachment_size' => $message->attachment_size,
                    'attachment_url' => $this->attachmentUrl($message->attachment_path),
                    'has_attachment' => $message->attachment_path !== null,
                    'attachment_is_image' => str_starts_with((string) $message->attachment_mime, 'image/'),
                    'created_at' => $message->created_at?->toDateTimeString(),
                    'user' => [
                        'id' => $message->user?->id,
                        'name' => $message->user?->full_name ?: $message->user?->name,
                        'email' => $message->user?->email,
                    ],
                ])
                ->values()
                ->all(),
            'order' => $ticket->order
                ? [
                    'id' => $ticket->order->id,
                    'reference' => $ticket->order->booking_reference ?: $ticket->order->external_booking_id ?: 'Order #'.$ticket->order->id,
                    'provider_name' => $ticket->order->provider_name,
                    'status' => $ticket->order->status,
                    'payment_status' => Schema::hasColumn('orders', 'payment_status')
                        ? $ticket->order->payment_status
                        : null,
                    'currency' => $ticket->order->currency,
                    'total_amount' => $ticket->order->total_amount !== null
                        ? number_format((float) $ticket->order->total_amount, 2, '.', '')
                        : null,
                    'created_at' => $ticket->order->created_at?->toDateTimeString(),
                ]
                : null,
        ];
    }

    /**
     * Persist the uploaded attachment and return metadata for the support message.
     *
     * @return array<string, int|string|null>
     */
    private function storeAttachment(?UploadedFile $attachment): array
    {
        if ($attachment === null || ! $this->supportService->supportsMessageAttachments()) {
            return [];
        }

        return [
            'attachment_path' => $attachment->store('support/attachments', 'public'),
            'attachment_name' => $attachment->getClientOriginalName(),
            'attachment_mime' => $this->resolveAttachmentMime($attachment),
            'attachment_size' => $attachment->getSize(),
        ];
    }

    private function resolveAttachmentMime(UploadedFile $attachment): ?string
    {
        $detectedMime = $attachment->getMimeType();

        if (is_string($detectedMime) && $detectedMime !== '' && $detectedMime !== 'application/octet-stream') {
            return $detectedMime;
        }

        $clientMime = $attachment->getClientMimeType();

        return is_string($clientMime) && $clientMime !== '' ? $clientMime : null;
    }

    private function attachmentUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function conversationState(?string $lastSenderType): ?string
    {
        return match ($lastSenderType) {
            'user' => 'waiting_for_support',
            'agent' => 'waiting_for_customer',
            default => null,
        };
    }
}