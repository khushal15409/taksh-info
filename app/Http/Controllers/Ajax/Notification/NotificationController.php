<?php

namespace App\Http\Controllers\Ajax\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\DeviceTokenRequest;
use App\Http\Requests\Notification\ListNotificationsRequest;
use App\Services\NotificationService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly NotificationService $notifications) {}

    /** GET /notifications/items?filter=&page=&per_page= (the API's GET /notifications; on the website that path is the page) */
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        return $this->success($this->notifications->list($request->user(), $request->input('filter', 'all'), (int) $request->input('per_page', 20)), 'api.notifications_fetched');
    }

    /** GET /notifications/unread-count */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(['unread_count' => $this->notifications->unreadCount($request->user())], 'api.notifications_fetched');
    }

    /** POST /notifications/{id}/read */
    public function markRead(Request $request, int $id): JsonResponse
    {
        return $this->success(['unread_count' => $this->notifications->markRead($request->user(), $id)], 'api.notification_marked_read');
    }

    /** POST /notifications/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        $this->notifications->markAllRead($request->user());

        return $this->success(['unread_count' => 0], 'api.notifications_marked_read');
    }

    /** POST /notifications/device-token {token, platform?} - always for the logged-in customer */
    public function storeDeviceToken(DeviceTokenRequest $request): JsonResponse
    {
        $id = $this->notifications->registerDevice($request->user(), $request->input('token'), $request->input('platform', 'web'));

        return $this->success(['id' => $id], 'Device registered');
    }

    /** DELETE /notifications/device-token {token} - this device only */
    public function destroyDeviceToken(DeviceTokenRequest $request): JsonResponse
    {
        $this->notifications->removeDevice($request->user(), $request->input('token'));

        return $this->success([], 'Device removed');
    }
}
