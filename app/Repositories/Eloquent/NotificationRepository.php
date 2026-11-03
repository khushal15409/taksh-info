<?php

namespace App\Repositories\Eloquent;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Same visibility rules as the admin API: a notification is shown once it is SENT for the customer
 * panel, was sent after the customer joined, and is either a broadcast or lists the customer.
 * Read state lives in notification_recipients; a broadcast gets its row when first read.
 */
class NotificationRepository implements NotificationRepositoryInterface
{
    private const PANEL = 'customer';

    public function paginate(User $user, string $filter, int $perPage): LengthAwarePaginator
    {
        $query = $this->visibleTo($user)->withExists(['recipients as is_read' => fn ($q) => $this->seenBy($q, $user->id)]);

        match ($filter) {
            'unread' => $query->whereDoesntHave('recipients', fn ($q) => $this->seenBy($q, $user->id)),
            'offers' => $query->whereIn('type', ['offer', 'promotion']),
            'orders' => $query->whereIn('type', ['order', 'delivery']),
            default => null,
        };

        return $query->orderByRaw('COALESCE(sent_at, created_at) DESC')->orderByDesc('id')->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return $this->visibleTo($user)->whereDoesntHave('recipients', fn ($q) => $this->seenBy($q, $user->id))->count();
    }

    public function findVisible(User $user, int $id): ?Notification
    {
        return $this->visibleTo($user)->find($id);
    }

    public function markSeen(Notification $notification, int $userId): void
    {
        NotificationRecipient::updateOrCreate(
            ['notification_id' => $notification->id, 'panel' => self::PANEL, 'recipient_user_id' => $userId],
            ['seen' => true]
        );
    }

    public function markAllSeen(User $user): void
    {
        $this->visibleTo($user)
            ->whereDoesntHave('recipients', fn ($q) => $this->seenBy($q, $user->id))
            ->get(['notifications.id'])
            ->each(fn (Notification $n) => $this->markSeen($n, $user->id));
    }

    public function saveDeviceToken(int $userId, string $token, string $platform): int
    {
        // One row per token: it moves to whoever is logged in on the device now.
        return DeviceToken::updateOrCreate(
            ['token_hash' => DeviceToken::hashOf($token)],
            ['user_id' => $userId, 'token' => $token, 'platform' => $platform, 'is_active' => true, 'last_used_at' => now()]
        )->id;
    }

    public function deleteDeviceToken(int $userId, string $token): void
    {
        DeviceToken::where('token_hash', DeviceToken::hashOf($token))->where('user_id', $userId)->delete();
    }

    private function visibleTo(User $user): Builder
    {
        return Notification::query()
            ->where('status', 'sent')
            ->where('send_to_which_panel', self::PANEL)
            ->where(fn ($q) => $q->whereNull('sent_at')->orWhere('sent_at', '>=', $user->created_at))
            ->where(fn ($q) => $q->where('send_to', 'all')
                ->orWhereHas('recipients', fn ($r) => $r->where('panel', self::PANEL)->where('recipient_user_id', $user->id)));
    }

    private function seenBy($query, int $userId)
    {
        return $query->where('panel', self::PANEL)->where('recipient_user_id', $userId)->where('seen', true);
    }
}
