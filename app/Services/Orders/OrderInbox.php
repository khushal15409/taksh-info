<?php

namespace App\Services\Orders;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Puts an order event into the customer's notification inbox (the Notifications page of the app and the website), as the
 * customer-only notification the inbox reads: type "order" / "delivery" so it shows under the Orders tab.
 * The same event is never written twice for a customer (taksh-admin and the website both write these, and a status can be
 * applied more than once). Never throws: the order that triggered it has already succeeded.
 */
class OrderInbox
{
    public static function add(int $userId, string $title, string $body, string $type = 'order'): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $exists = Notification::query()
                ->where('send_to_which_panel', 'customer')->where('title', $title)->where('message', $body)
                ->whereHas('recipients', fn ($r) => $r->where('panel', 'customer')->where('recipient_user_id', $userId))
                ->exists();
            if ($exists) {
                return;
            }

            DB::transaction(function () use ($userId, $title, $body, $type) {
                $n = Notification::create([
                    'title' => $title,
                    'message' => $body,
                    'type' => $type,
                    'send_to' => 'custom',
                    'send_to_which_panel' => 'customer',
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                NotificationRecipient::create([
                    'notification_id' => $n->id,
                    'panel' => 'customer',
                    'recipient_user_id' => $userId,
                    'seen' => false,
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('Order notification could not be saved to the inbox', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }
}
