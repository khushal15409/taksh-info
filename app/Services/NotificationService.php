<?php

namespace App\Services;

use App\Exceptions\NotificationNotFoundException;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Repositories\Contracts\NotificationRepositoryInterface;

class NotificationService
{
    public function __construct(private readonly NotificationRepositoryInterface $notifications) {}

    public function list(User $user, string $filter, int $perPage): array
    {
        $page = $this->notifications->paginate($user, $filter, $perPage);

        return [
            'items' => NotificationResource::collection(collect($page->items()))->resolve(),
            'unread_count' => $this->notifications->unreadCount($user),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }

    /** One page of the inbox for the notifications page; the items are the same shape as list(). */
    public function page(User $user, string $filter, int $perPage): LengthAwarePaginator
    {
        $page = $this->notifications->paginate($user, $filter, $perPage);

        return $page->setCollection(collect(NotificationResource::collection(collect($page->items()))->resolve()));
    }

    /** One notification for the detail page, marked as read. */
    public function open(User $user, int $id): array
    {
        $notification = $this->notifications->findVisible($user, $id) ?? throw new NotificationNotFoundException;
        $this->notifications->markSeen($notification, $user->id);

        return ['is_read' => true] + NotificationResource::make($notification)->resolve();
    }

    public function unreadCount(User $user): int
    {
        return $this->notifications->unreadCount($user);
    }

    public function markRead(User $user, int $id): int
    {
        $notification = $this->notifications->findVisible($user, $id) ?? throw new NotificationNotFoundException;
        $this->notifications->markSeen($notification, $user->id);

        return $this->notifications->unreadCount($user);
    }

    public function markAllRead(User $user): void
    {
        $this->notifications->markAllSeen($user);
    }

    public function registerDevice(User $user, string $token, string $platform): int
    {
        return $this->notifications->saveDeviceToken($user->id, $token, $platform);
    }

    public function removeDevice(User $user, string $token): void
    {
        $this->notifications->deleteDeviceToken($user->id, $token);
    }
}
