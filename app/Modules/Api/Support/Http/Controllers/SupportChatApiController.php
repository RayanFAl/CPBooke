<?php

namespace App\Modules\Api\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Support\Events\SupportMessageBroadcasted;
use App\Modules\Admin\Support\Events\SupportTicketUpdatedBroadcasted;
use App\Modules\Admin\Support\Events\SupportTypingBroadcasted;
use App\Modules\Admin\Support\Services\SupportService;
use App\Modules\Admin\Support\SupportChatPayloadBuilder;
use App\Modules\Api\Support\Http\Requests\StoreSupportChatMessageRequest;
use App\Modules\Api\Support\Http\Requests\StoreSupportSeenRequest;
use App\Modules\Api\Support\Http\Requests\StoreSupportTypingRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class SupportChatApiController extends Controller
{
    public function __construct(
        private readonly SupportService $supportService,
        private readonly SupportChatPayloadBuilder $payloadBuilder,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isCustomerAccount(), 403);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);
        $tickets = $this->supportService->getChatTicketsForCustomer($request->user(), $perPage);

        return ApiResponse::success(
            [
                'tickets' => collect($tickets->items())
                    ->map(fn ($ticket): array => $this->payloadBuilder->ticket($ticket, 'customer'))
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

        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);

        return ApiResponse::success(
            [
                'ticket' => $this->payloadBuilder->ticket($supportTicket, 'customer'),
            ],
            'Support chat ticket fetched successfully.',
        );
    }

    public function messages(Request $request, int $ticket): JsonResponse
    {
        abort_unless($request->user()?->isCustomerAccount(), 403);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);
        $messages = $this->supportService->getChatMessagesForCustomer($request->user(), $ticket, $perPage);

        return ApiResponse::success(
            [
                'ticket' => $this->payloadBuilder->ticket($supportTicket, 'customer'),
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
        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);

        $this->supportService->addCustomerMessage(
            $supportTicket,
            (string) $request->input('message', ''),
            $request->user()->id,
            $this->storeAttachment($request->file('attachment')) + [
                'reply_to_id' => $request->input('reply_to_id'),
                'metadata' => $request->input('metadata'),
            ],
        );

        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);
        $message = $supportTicket->messages->last();

        if ($message !== null) {
            event(new SupportMessageBroadcasted(
                $supportTicket->id,
                $this->payloadBuilder->ticket($supportTicket),
                $this->payloadBuilder->message($message),
            ));
        }

        event(new SupportTicketUpdatedBroadcasted(
            $supportTicket->id,
            $this->payloadBuilder->ticket($supportTicket),
        ));

        return ApiResponse::success(
            [
                'ticket' => $this->payloadBuilder->ticket($supportTicket, 'customer'),
                'message' => $message ? $this->payloadBuilder->message($message) : null,
            ],
            'Support chat message sent successfully.',
        );
    }

    public function typing(StoreSupportTypingRequest $request, int $ticket): JsonResponse
    {
        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);

        $typingPayload = $this->supportService->storeTypingState(
            $supportTicket,
            $request->user(),
            $request->boolean('typing', true),
            $request->input('metadata', []),
        );

        event(new SupportTypingBroadcasted($supportTicket->id, $typingPayload));

        return ApiResponse::success(
            [
                'ticket' => $this->payloadBuilder->ticket($supportTicket, 'customer'),
                'typing' => $typingPayload,
            ],
            'Support typing state updated successfully.',
        );
    }

    public function seen(StoreSupportSeenRequest $request, int $ticket): JsonResponse
    {
        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);

        $seenCount = $this->supportService->markMessagesSeenForCustomer(
            $supportTicket,
            $request->user(),
            $request->input('message_ids', []),
        );

        $supportTicket = $this->supportService->getChatTicketForCustomer($request->user(), $ticket);

        event(new SupportTicketUpdatedBroadcasted(
            $supportTicket->id,
            $this->payloadBuilder->ticket($supportTicket),
        ));

        return ApiResponse::success(
            [
                'ticket' => $this->payloadBuilder->ticket($supportTicket, 'customer'),
                'seen_count' => $seenCount,
            ],
            'Support messages marked as seen successfully.',
        );
    }

    /**
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
}