<?php

namespace App\Modules\Api\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Modules\Api\Support\Http\Requests\CreateSupportTicketRequest;
use App\Modules\Api\Support\Http\Requests\StoreSupportReplyRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Support\Presenters\SupportApiPayloadBuilder;
use App\Modules\Support\Services\SupportService;
use App\Modules\Support\Storage\SupportAttachmentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportApiController extends Controller
{
    public function __construct(
        private readonly SupportService $supportService,
        private readonly SupportApiPayloadBuilder $payloadBuilder,
        private readonly SupportAttachmentStorage $supportAttachmentStorage,
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
            ['ticket' => $ticket ? $this->payloadBuilder->detail($ticket) : null],
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
        ], $request->string('message')->value(), $request->user()->id, $this->supportAttachmentStorage->store($request->file('attachment')));

        $ticket = $this->supportService->getTicketDetails($request->user(), $result['ticket']->id);

        return ApiResponse::success(
            ['ticket' => $this->payloadBuilder->detail($ticket)],
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
                    ->map(fn (SupportTicket $ticket): array => $this->payloadBuilder->summary($ticket))
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
            ['ticket' => $this->payloadBuilder->detail($ticket)],
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
            $this->supportAttachmentStorage->store($request->file('attachment')),
        );

        $ticket = $this->supportService->getTicketDetails($request->user(), $id);

        return ApiResponse::success(
            ['ticket' => $this->payloadBuilder->detail($ticket)],
            'Support reply added successfully.',
        );
    }
}
