<?php

namespace App\Modules\Support\Presenters;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Modules\Support\Services\SupportService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SupportApiPayloadBuilder
{
    public function __construct(
        private readonly SupportService $supportService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(SupportTicket $ticket): array
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
            'has_unread_for_customer' => $lastSenderType === 'agent',
            'conversation_state' => $this->conversationState($lastSenderType),
            'sla_status' => $this->supportService->slaStatusFor($ticket),
            'sla_risk' => $this->supportService->slaRiskFor($ticket),
            'created_at' => $ticket->created_at?->toDateTimeString(),
            'updated_at' => $ticket->updated_at?->toDateTimeString(),
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
     * @return array<string, mixed>
     */
    public function detail(SupportTicket $ticket): array
    {
        return [
            ...$this->summary($ticket),
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
