<?php

namespace App\Modules\Api\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Admin\Support\Events\SupportMessageBroadcasted;
use App\Modules\Admin\Support\Events\SupportTicketUpdatedBroadcasted;
use App\Modules\Admin\Support\Events\SupportTypingBroadcasted;
use App\Modules\Api\Support\Http\Requests\StoreSupportChatMessageRequest;
use App\Modules\Api\Support\Http\Requests\StoreSupportSeenRequest;
use App\Modules\Api\Support\Http\Requests\StoreSupportTypingRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Support\Presenters\SupportChatPayloadBuilder;
use App\Modules\Support\Services\SupportBroadcastService;
use App\Modules\Support\Services\SupportService;
use App\Modules\Support\Storage\SupportAttachmentStorage;
use App\Support\Platform\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportChatApiController extends Controller
{
    public function __construct(
        private readonly SupportService $supportService,
        private readonly SupportChatPayloadBuilder $payloadBuilder,
        private readonly SupportBroadcastService $supportBroadcastService,
        private readonly SupportAttachmentStorage $supportAttachmentStorage,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isCustomerAccount(), 403);

        if ($disabled = $this->supportChatDisabledResponse()) {
            return $disabled;
        }

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);
        $tickets = $this->supportService->getChatTicketsForCustomer($request->user(), $perPage);

        return ApiResponse::success(
            [
                'tickets' => collect($tickets->items())
                    ->map(fn ($ticket): array => $this->payloadBuilder->ticket($ticket, 'user'))
                    ->values()
                    ->all(),
            ],
            'Support chat tickets fetched successfully.',
            [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        );
    }

    public function show(Request $request, int $ticket): JsonResponse
    {
        abort_unless($request->user()?->isCustomerAccount(), 403);

        if ($disabled = $this->supportChatDisabledResponse()) {
            return $disabled;
        }

        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);

        return ApiResponse::success(
            [
                'ticket' => $this->payloadBuilder->ticket($supportTicket, 'user'),
            ],
            'Support chat ticket fetched successfully.',
        );
    }

    public function messages(Request $request, int $ticket): JsonResponse
    {
        abort_unless($request->user()?->isCustomerAccount(), 403);

        if ($disabled = $this->supportChatDisabledResponse()) {
            return $disabled;
        }

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);
        $messages = $this->supportService->getChatMessagesForCustomer($request->user(), $ticket, $perPage);

        return ApiResponse::success(
            [
                'ticket' => $this->payloadBuilder->ticket($supportTicket, 'user'),
                'messages' => $this->payloadBuilder->messages($messages->items()),
            ],
            'Support chat messages fetched successfully.',
            [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        );
    }

    public function storeMessage(StoreSupportChatMessageRequest $request, int $ticket): JsonResponse
    {
        if ($disabled = $this->supportChatDisabledResponse()) {
            return $disabled;
        }

        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);

        $this->supportService->addCustomerMessage(
            $supportTicket,
            (string) $request->input('message', ''),
            $request->user()->id,
            $this->supportAttachmentStorage->store($request->file('attachment')) + [
                'reply_to_id' => $request->input('reply_to_id'),
                'metadata' => $request->input('metadata'),
            ],
        );

        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);
        $message = $supportTicket->messages->last();

        if ($message !== null) {
            $this->supportBroadcastService->dispatch(new SupportMessageBroadcasted(
                $supportTicket->id,
                $this->payloadBuilder->ticket($supportTicket),
                $this->payloadBuilder->message($message),
            ));
        }

        $this->supportBroadcastService->dispatch(new SupportTicketUpdatedBroadcasted(
            $supportTicket->id,
            $this->payloadBuilder->ticket($supportTicket),
        ));

        return ApiResponse::success(
            [
                'ticket' => $this->payloadBuilder->ticket($supportTicket, 'user'),
                'message' => $message ? $this->payloadBuilder->message($message) : null,
            ],
            'Support chat message sent successfully.',
        );
    }

    public function showTyping(StoreSupportTypingRequest $request, int $ticket): JsonResponse
    {
        if ($disabled = $this->supportChatDisabledResponse()) {
            return $disabled;
        }

        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);

        if ($request->hasTypingInput()) {
            $typingPayload = $this->broadcastCustomerTyping(
                $supportTicket,
                $request->user(),
                $request->resolvedTyping(),
                $request->input('metadata', []),
            );
            $isTyping = (bool) $typingPayload['typing'];
        } else {
            $isTyping = $this->supportService->isAgentTyping($supportTicket);
        }

        return ApiResponse::success(
            [
                'is_typing' => $isTyping,
            ],
            'Support typing state fetched successfully.',
        );
    }

    public function typing(StoreSupportTypingRequest $request, int $ticket): JsonResponse
    {
        if ($disabled = $this->supportChatDisabledResponse()) {
            return $disabled;
        }

        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);

        $typingPayload = $this->broadcastCustomerTyping(
            $supportTicket,
            $request->user(),
            $request->resolvedTyping(),
            $request->input('metadata', []),
        );

        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);

        return ApiResponse::success(
            [
                'is_typing' => (bool) $typingPayload['typing'],
                'ticket' => $this->payloadBuilder->ticket($supportTicket, 'user'),
                'typing' => $typingPayload,
            ],
            'Support typing state updated successfully.',
        );
    }

    public function seen(StoreSupportSeenRequest $request, int $ticket): JsonResponse
    {
        if ($disabled = $this->supportChatDisabledResponse()) {
            return $disabled;
        }

        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);

        $seenCount = $this->supportService->markMessagesSeenForCustomer(
            $supportTicket,
            $request->user(),
            $request->input('message_ids', []),
        );

        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);

        $this->supportBroadcastService->dispatch(new SupportTicketUpdatedBroadcasted(
            $supportTicket->id,
            $this->payloadBuilder->ticket($supportTicket),
        ));

        return ApiResponse::success(
            [
                'ticket' => $this->payloadBuilder->ticket($supportTicket, 'user'),
                'seen_count' => $seenCount,
            ],
            'Support messages marked as seen successfully.',
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function broadcastCustomerTyping(
        SupportTicket $supportTicket,
        User $actor,
        bool $typing,
        array $metadata = [],
    ): array {
        $typingPayload = $this->supportService->storeTypingState(
            $supportTicket,
            $actor,
            $typing,
            $metadata,
        );

        $this->supportBroadcastService->dispatch(new SupportTypingBroadcasted($supportTicket->id, $typingPayload));

        return $typingPayload;
    }

    private function supportChatDisabledResponse(): ?JsonResponse
    {
        if (PlatformSettings::supportChatEnabled()) {
            return null;
        }

        return ApiResponse::error(
            'Customer support chat is temporarily unavailable.',
            [],
            'support_chat_disabled',
            403,
        );
    }
}
