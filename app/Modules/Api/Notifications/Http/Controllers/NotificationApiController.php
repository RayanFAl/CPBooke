<?php

namespace App\Modules\Api\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Modules\Api\Notifications\Http\Requests\DestroyNotificationDeviceRequest;
use App\Modules\Api\Notifications\Http\Requests\MarkNotificationsReadRequest;
use App\Modules\Api\Notifications\Http\Requests\RegisterNotificationDeviceRequest;
use App\Modules\Api\Notifications\Http\Requests\UpdateNotificationDeviceRequest;
use App\Modules\Api\Notifications\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Modules\Api\Resources\PassengerNotificationResource;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Support\NotificationChannels;
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
        $perPage = (int) min(max($request->integer('per_page', 15), 1), 50);
        $unreadOnly = $request->boolean('unread_only');

        $notifications = $this->notificationService->paginateForUser(
            $request->user(),
            $perPage,
            $unreadOnly,
        );

        $items = collect($notifications->items())
            ->map(fn (UserNotification $notification): array => PassengerNotificationResource::make($notification)->resolve($request))
            ->values()
            ->all();

        return ApiResponse::success(
            $items,
            'Notifications fetched successfully.',
            [
                'page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $this->notificationService->unreadCountForUser($request->user()),
            ],
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['unread_count' => $this->notificationService->unreadCountForUser($request->user())],
            'Unread count fetched successfully.',
        );
    }

    /**
     * Legacy unread summary endpoint (kept for older clients).
     */
    public function unread(Request $request): JsonResponse
    {
        $summary = $this->notificationService->unreadSummaryForUser($request->user());

        return ApiResponse::success(
            [
                'count' => $summary['count'],
                'unread_count' => $summary['count'],
                'notifications' => collect($summary['items'])
                    ->map(fn (UserNotification $notification): array => PassengerNotificationResource::make($notification)->resolve($request))
                    ->values()
                    ->all(),
            ],
            'Unread notifications fetched successfully.',
        );
    }

    public function markOneAsRead(Request $request, int|string $notification): JsonResponse
    {
        $updated = $this->notificationService->markOneAsRead($request->user(), $notification);

        return ApiResponse::success(
            [
                'id' => (string) $updated->id,
                'is_read' => true,
            ],
            'Notification marked as read successfully.',
        );
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $this->notificationService->markAllAsRead($request->user());

        return ApiResponse::success(
            ['updated' => $updated],
            'All notifications marked as read successfully.',
        );
    }

    /**
     * Legacy bulk mark-as-read endpoint.
     */
    public function markAsRead(MarkNotificationsReadRequest $request): JsonResponse
    {
        $markedCount = $this->notificationService->markAsRead(
            $request->user(),
            $request->validated('notification_ids', []),
            $request->boolean('mark_all'),
        );

        return ApiResponse::success(
            [
                'marked_count' => $markedCount,
                'updated' => $markedCount,
            ],
            'Notifications marked as read successfully.',
        );
    }

    public function destroy(Request $request, int|string $notification): JsonResponse
    {
        $this->notificationService->deleteOne($request->user(), $notification);

        return ApiResponse::success(
            ['deleted' => true],
            'Notification deleted successfully.',
        );
    }

    public function clear(Request $request): JsonResponse
    {
        $deleted = $this->notificationService->deleteAllForUser($request->user());

        return ApiResponse::success(
            ['deleted' => $deleted],
            'Notifications cleared successfully.',
        );
    }

    public function registerDevice(RegisterNotificationDeviceRequest $request): JsonResponse
    {
        $device = $this->notificationService->registerDevice(
            $request->user(),
            $request->validated('device_token'),
            $request->validated('platform'),
            $request->validated('channel', NotificationChannels::PUSH),
        );

        return ApiResponse::success(
            [
                'id' => $device->id,
                'platform' => $device->platform,
                'channel' => $device->channel,
                'is_active' => $device->is_active,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            ],
            'Device registered successfully.',
            [],
            201,
        );
    }

    public function updateDevice(UpdateNotificationDeviceRequest $request): JsonResponse
    {
        $device = $this->notificationService->setDeviceEnabled(
            $request->user(),
            $request->validated('device_token'),
            $request->boolean('enabled'),
        );

        return ApiResponse::success(
            [
                'device_token' => $request->validated('device_token'),
                'enabled' => (bool) $device->is_active,
                'is_active' => (bool) $device->is_active,
            ],
            'Device updated successfully.',
        );
    }

    public function destroyDevice(DestroyNotificationDeviceRequest $request): JsonResponse
    {
        $this->notificationService->disableDevice(
            $request->user(),
            $request->validated('device_token'),
            delete: true,
        );

        return ApiResponse::success(
            ['deleted' => true],
            'Device unregistered successfully.',
        );
    }

    public function preferences(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->notificationService->passengerPreferences($request->user()),
            'Notification preferences fetched successfully.',
        );
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->notificationService->updatePassengerPreferences(
                $request->user(),
                $request->validated(),
            ),
            'Notification preferences updated successfully.',
        );
    }

    public function pushTest(Request $request): JsonResponse
    {
        $result = $this->notificationService->sendTestPush(
            $request->user(),
            $request->string('title')->toString() ?: null,
            $request->string('body')->toString() ?: null,
        );

        /** @var UserNotification $notification */
        $notification = $result['notification'];

        return ApiResponse::success(
            [
                'notification' => PassengerNotificationResource::make($notification)->resolve($request),
                'push' => $result['push'],
            ],
            'Test push processed successfully.',
        );
    }
}
