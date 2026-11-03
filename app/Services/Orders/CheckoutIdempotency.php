<?php

namespace App\Services\Orders;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Makes "Confirm Order" safe to press twice. Every rendered checkout page carries its own token. The first submit with a token places the
 * order and remembers which orders it made; a second submit with the same token (a double click, a refresh of the POST, the back button)
 * is answered with the same orders instead of placing another. A lock per customer makes two submits that arrive at the same moment take
 * turns, so the second one always sees the first one's result. A different token (a new page) is a new checkout: the cart decides if
 * there is still anything to order.
 */
class CheckoutIdempotency
{
    private const REMEMBER_MINUTES = 30;
    private const LOCK_SECONDS = 60;
    private const WAIT_SECONDS = 15;

    /**
     * @param  callable(): list<int>  $place  places the order(s) and returns their ids (the last one is the one to continue with)
     * @return array{order_ids: list<int>, replayed: bool}
     */
    public function run(User $user, string $token, callable $place): array
    {
        $key = "checkout.placed.{$user->id}.{$token}";

        if ($done = Cache::get($key)) {
            return ['order_ids' => $done, 'replayed' => true];
        }

        // the other submit holds the lock while it places; when it lets go its result is waiting under $key
        return Cache::lock("checkout.lock.{$user->id}", self::LOCK_SECONDS)->block(self::WAIT_SECONDS, function () use ($key, $place) {
            if ($done = Cache::get($key)) {
                return ['order_ids' => $done, 'replayed' => true];
            }

            $ids = $place();
            Cache::put($key, $ids, now()->addMinutes(self::REMEMBER_MINUTES));

            return ['order_ids' => $ids, 'replayed' => false];
        });
    }
}
