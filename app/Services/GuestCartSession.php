<?php

namespace App\Services;

use App\Models\User;
use App\Support\CartOwner;
use Illuminate\Support\Str;

/**
 * The website keeps a guest's cart token in the server session (the app sends it from the phone), so a
 * browser can only ever reach its own guest cart. After login the guest cart is merged into the customer's.
 */
class GuestCartSession
{
    private const KEY = 'cart.guest_token';

    public function token(): ?string
    {
        return session(self::KEY);
    }

    /** The token of this session, made the first time it is needed. */
    public function ensure(): string
    {
        return $this->token() ?? tap(Str::random(32), fn ($t) => session([self::KEY => $t]));
    }

    public function forget(): void
    {
        session()->forget(self::KEY);
    }

    public function owner(?User $user): CartOwner
    {
        return new CartOwner($user?->id, $user ? null : $this->token());
    }
}
