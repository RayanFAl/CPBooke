<?php

namespace App\Modules\Support\Presenters;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SupportChatPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function ticket(SupportTicket $ticket, ?string $viewerType = null): array
    {
        $relations = ['latestMessage.user:id,name,full_name,email,account_type'];

        if ($viewerType === 'agent') {
            $relations[] = 'assignee:id,name,full_name,email';
        }

        $ticket->loadMissing($relations);

        $lastMessage = $ticket->latestMessage;
        $lastSenderType = $lastMessage ? $this->resolveSenderType($lastMessage) : null;

        $payload = [
            'id' => $ticket->id,
            'code' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'unread_count' => $this->unreadCount($ticket, $viewerType),
            'last_message_at' => $lastMessage?->created_at?->toDateTimeString(),
            'conversation_state' => $this->conversationState($ticket, $lastSenderType),
        ];

        if ($viewerType === 'agent') {
            $payload['assigned_agent'] = $ticket->assignee ? [
                'id' => $ticket->assignee->id,
                'name' => $ticket->assignee->full_name ?: $ticket->assignee->name,
                'avatar' => null,
            ] : null;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function message(SupportMessage $message): array
    {
        $message->loadMissing([
            'user:id,name,full_name,email,account_type',
        ]);

        return [
            'id' => $message->id,
            'text' => $message->message,
            'sender_type' => $this->resolveSenderType($message),
            'message_type' => $this->resolveMessageType($message),
            'attachment' => $this->attachmentPayload($message),
            'reply_to_id' => $message->reply_to_id,
            'delivered_at' => $message->delivered_at?->toDateTimeString(),
            'seen_at' => $message->seen_at?->toDateTimeString(),
            'created_at' => $message->created_at?->toDateTimeString(),
            'sender' => [
                'id' => $message->user?->id,
                'name' => $message->user?->full_name ?: $message->user?->name ?: 'System',
                'avatar' => null,
            ],
        ];
    }

    /**
     * @param  iterable<SupportMessage>  $messages
     * @return array<int, array<string, mixed>>
     */
    public function messages(iterable $messages): array
    {
        $payload = [];

        foreach ($messages as $message) {
            $payload[] = $this->message($message);
        }

        return $payload;
    }

    private function conversationState(SupportTicket $ticket, ?string $lastSenderType): ?string
    {
        if ($ticket->status === 'resolved') {
            return 'resolved';
        }

        if ($ticket->status === 'closed') {
            return 'closed';
        }

        return match ($lastSenderType) {
            'customer' => 'waiting_for_support',
            'agent' => 'waiting_for_customer',
            default => null,
        };
    }

    private function unreadCount(SupportTicket $ticket, ?string $viewerType): int
    {
        if ($viewerType === null) {
            return $this->supportsSeenAtColumn()
                ? $ticket->messages()->whereNull('seen_at')->count()
                : 0;
        }

        if ($this->supportsSeenAtColumn() && $this->supportsSenderTypeColumn()) {
            $targetSenderType = $viewerType === 'customer' ? 'agent' : 'customer';

            return $ticket->messages()
                ->whereNull('seen_at')
                ->where('sender_type', $targetSenderType)
                ->count();
        }

        $ticket->loadMissing('latestMessage.user:id,name,full_name,email,account_type');

        $lastMessage = $ticket->latestMessage;

        if ($lastMessage === null) {
            return 0;
        }

        $lastSenderType = $this->resolveSenderType($lastMessage);

        return ($viewerType === 'customer' && $lastSenderType === 'agent')
            || ($viewerType === 'agent' && $lastSenderType === 'customer')
                ? 1
                : 0;
    }

    private function resolveSenderType(SupportMessage $message): string
    {
        if ($message->sender_type !== null && $message->sender_type !== '') {
            return $message->sender_type;
        }

        if ($message->is_internal || $message->user_id === null) {
            return 'system';
        }

        return $message->user?->isAdminAccount() ? 'agent' : 'customer';
    }

    private function resolveMessageType(SupportMessage $message): string
    {
        if ($message->message_type !== null && $message->message_type !== '') {
            return $message->message_type;
        }

        if ($message->is_internal) {
            return 'system';
        }

        if ($message->attachment_mime !== null && $message->attachment_mime !== '') {
            if (str_starts_with($message->attachment_mime, 'image/')) {
                return 'image';
            }

            if (str_starts_with($message->attachment_mime, 'video/')) {
                return 'video';
            }

            return 'file';
        }

        return 'text';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function attachmentPayload(SupportMessage $message): ?array
    {
        if ($message->attachment_path === null) {
            return null;
        }

        return [
            'path' => $message->attachment_path,
            'name' => $message->attachment_name,
            'mime' => $message->attachment_mime,
            'size' => $message->attachment_size,
            'url' => Storage::disk('public')->url($message->attachment_path),
            'is_image' => str_starts_with((string) $message->attachment_mime, 'image/'),
            'is_video' => str_starts_with((string) $message->attachment_mime, 'video/'),
        ];
    }

    private function supportsSenderTypeColumn(): bool
    {
        return Schema::hasTable('support_messages')
            && Schema::hasColumn('support_messages', 'sender_type');
    }

    private function supportsSeenAtColumn(): bool
    {
        return Schema::hasTable('support_messages')
            && Schema::hasColumn('support_messages', 'seen_at');
    }
}