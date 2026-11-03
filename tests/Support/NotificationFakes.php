<?php

namespace Tests\Support;

use App\Models\Notification;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class FakeNotificationRepository implements NotificationRepositoryInterface
{
    /** @var array<int, Notification> visible to everyone */
    public array $rows = [];

    /** @var array<string, bool> "userId:notificationId" => seen */
    public array $seen = [];

    /** @var array<string, array{user_id:int, platform:string}> token => owner */
    public array $tokens = [];

    public string $lastFilter = '';

    public function add(int $id, string $type = 'offer', array $attrs = []): Notification
    {
        return $this->rows[$id] = (new Notification)->forceFill($attrs + ['id' => $id, 'type' => $type, 'title' => "N$id", 'message' => "Body $id", 'sent_at' => now()->subMinutes(10 - $id)]);
    }

    public function paginate(User $user, string $filter, int $perPage): LengthAwarePaginator
    {
        $this->lastFilter = $filter;
        $rows = collect($this->rows)->filter(fn ($n) => match ($filter) {
            'unread' => ! $this->isSeen($user->id, $n->id),
            'offers' => in_array($n->type, ['offer', 'promotion']),
            'orders' => in_array($n->type, ['order', 'delivery']),
            default => true,
        })->sortByDesc('id')->values()->map(function ($n) use ($user) {
            $c = clone $n;
            $c->is_read = $this->isSeen($user->id, $n->id);

            return $c;
        });

        return new Paginator($rows, $rows->count(), $perPage, 1);
    }

    public function unreadCount(User $user): int
    {
        return collect($this->rows)->filter(fn ($n) => ! $this->isSeen($user->id, $n->id))->count();
    }

    public function findVisible(User $user, int $id): ?Notification
    {
        return $this->rows[$id] ?? null;
    }

    public function markSeen(Notification $notification, int $userId): void
    {
        $this->seen["$userId:{$notification->id}"] = true;
    }

    public function markAllSeen(User $user): void
    {
        foreach ($this->rows as $n) {
            $this->markSeen($n, $user->id);
        }
    }

    public function saveDeviceToken(int $userId, string $token, string $platform): int
    {
        $this->tokens[$token] = ['user_id' => $userId, 'platform' => $platform];

        return count($this->tokens);
    }

    public function deleteDeviceToken(int $userId, string $token): void
    {
        if (($this->tokens[$token]['user_id'] ?? null) === $userId) {
            unset($this->tokens[$token]);
        }
    }

    private function isSeen(int $userId, int $id): bool
    {
        return $this->seen["$userId:$id"] ?? false;
    }
}
