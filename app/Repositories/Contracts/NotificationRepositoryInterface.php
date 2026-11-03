<?php

namespace App\Repositories\Contracts;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    /** Newest first; each row carries a boolean `is_read`. $filter: all|unread|offers|orders. */
    public function paginate(User $user, string $filter, int $perPage): LengthAwarePaginator;

    public function unreadCount(User $user): int;

    /** A notification this customer is allowed to see. */
    public function findVisible(User $user, int $id): ?Notification;

    public function markSeen(Notification $notification, int $userId): void;

    /** Marks every unread visible notification as read. */
    public function markAllSeen(User $user): void;

    public function saveDeviceToken(int $userId, string $token, string $platform): int;

    public function deleteDeviceToken(int $userId, string $token): void;
}
