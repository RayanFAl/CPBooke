<?php

namespace App\Modules\Api\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Modules\Api\Notifications\Http\Requests\MarkNotificationsReadRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationApiController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->paginateForUser(
            $request->user(),
            (int) min(max($request->integer('per_page', 15), 1), 50),
        );

        return ApiResponse::success(
            [
                'notifications' => collect($notifications->items())
                    ->map(fn (UserNotification $notification): array => $this->payload($notification))
                    ->values()
                    ->all(),
            ],
            'Notifications fetched successfully.',
            [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        );
    }

    public function unread(Request $request): JsonResponse
    {
        $summary = $this->notificationService->unreadSummaryForUser($request->user());

        return ApiResponse::success(
            [
                'count' => $summary['count'],
                'notifications' => collect($summary['items'])
                    ->map(fn (UserNotification $notification): array => $this->payload($notification))
                    ->values()
                    ->all(),
            ],
            'Unread notifications fetched successfully.',
        );
    }

    public function markAsRead(MarkNotificationsReadRequest $request): JsonResponse
    {
        $markedCount = $this->notificationService->markAsRead(
            $request->user(),
            $request->validated('notification_ids', []),
            $request->boolean('mark_all'),
        );

        return ApiResponse::success(
            ['marked_count' => $markedCount],
            'Notifications marked as read successfully.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(UserNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'template_code' => $notification->template_code,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data ?? [],
            'related_entity' => [
                'type' => $notification->related_type,
                'id' => $notification->related_id,
            ],
            'is_read' => ! $notification->isUnread(),
            'read_at' => $notification->read_at?->toDateTimeString(),
            'delivered_at' => $notification->delivered_at?->toDateTimeString(),
            'created_at' => $notification->created_at?->toDateTimeString(),
        ];
    }
}