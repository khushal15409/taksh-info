<?php

namespace App\Support;

/** Who a cart belongs to: a signed-in customer, or a guest identified by the token kept in their session. */
final class CartOwner
{
    public function __construct(public readonly ?int $userId, public readonly ?string $guestToken) {}

    public function isGuest(): bool
    {
        return $this->userId === null;
    }
}
