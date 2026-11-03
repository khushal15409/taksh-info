<?php

namespace App\Services;

use App\Repositories\Contracts\WishlistRepositoryInterface;
use Illuminate\Contracts\Auth\Factory as Auth;

/**
 * What the page header shows about the visitor - the cart count and total, and the saved-product hearts - worked out on the server so
 * the first paint is already right. Read at most once per request (it is shared with the layout and the product cards), and only
 * It is an extra: if it cannot be read the page still renders, with an empty cart and no hearts.
 */
class HeaderStateService
{
    private ?array $state = null;

    /** The request the state was read for (a long-lived process must not carry one visitor's cart into the next request). */
    private ?int $forRequest = null;

    public function __construct(
        private readonly CartService $cart,
        private readonly GuestCartSession $guest,
        private readonly WishlistRepositoryInterface $wishlist,
        private readonly Auth $auth,
    ) {}

    /** @return array{cart_count: int, cart_total: float, wish_ids: list<int>} */
    public function get(): array
    {
        $request = spl_object_id(request());
        if ($this->state === null || $this->forRequest !== $request) {
            $this->state = $this->build();
            $this->forRequest = $request;
        }

        return $this->state;
    }

    public function isSaved(int $productId): bool
    {
        return in_array($productId, $this->get()['wish_ids'], true);
    }

    private function build(): array
    {
        $empty = ['cart_count' => 0, 'cart_total' => 0.0, 'wish_ids' => []];

        $user = $this->auth->guard()->user();

        try {
            // a guest who has never added anything has no cart token yet: nothing to read
            if ($user || $this->guest->token()) {
                $cart = $this->cart->get($this->guest->owner($user), 'normal')['cart'];
                $empty['cart_count'] = (int) collect($cart['items'])->sum('qty');
                $empty['cart_total'] = (float) $cart['total_with_delivery'];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $empty['wish_ids'] = $user ? $this->wishlist->productIds($user->id) : [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $empty;
    }
}
