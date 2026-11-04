<?php

namespace App\Http\Controllers\Web\Cart;

use App\Exceptions\ApiException;
use App\Http\Controllers\Web\WebController;
use App\Http\Requests\Web\Cart\CartCouponRequest;
use App\Http\Requests\Web\Cart\CartLineRequest;
use App\Services\CartService;
use App\Services\GuestCartSession;
use App\Support\CartOwner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The shopping cart page. Standard and Quick are separate carts (?type=normal|30_min). Every change is a small POST/DELETE that comes
 * back to the same cart with a toast; the cart script refreshes just the cart area in place when it can (see modules/cart.js).
 * A guest's cart belongs to their session, and is merged into the customer's cart when they log in.
 */
class CartPageController extends WebController
{
    public function __construct(
        private readonly CartService $cart,
        private readonly GuestCartSession $guest,
    ) {}

    public function show(Request $request): View
    {
        $type = $this->typeOf($request->query('type'));
        $owner = $this->owner($request);
        $carts = ['normal' => $this->cart->get($owner, 'normal')['cart'], '30_min' => $this->cart->get($owner, '30_min')['cart']];

        // no tab asked for: open the one that has something (Quick when Standard is empty)
        if (! $request->query('type') && ! $carts['normal']['items'] && $carts['30_min']['items']) {
            $type = '30_min';
        }

        return $this->page('cart.index', ['type' => $type, 'carts' => $carts]);
    }

    /** POST /cart/lines/{id}/quantity {qty, type} - a quantity of 0 removes the line */
    public function updateLine(CartLineRequest $request, int $id): RedirectResponse
    {
        $qty = (int) $request->validated('qty');

        return $this->act($request->type(), fn () => $qty < 1
            ? $this->cart->remove($this->owner($request), $id, $request->type())
            : $this->cart->update($this->owner($request), $id, $qty, $request->type()), null);
    }

    /** DELETE /cart/lines/{id}?type= */
    public function removeLine(Request $request, int $id): RedirectResponse
    {
        $type = $this->typeOf($request->input('type'));

        return $this->act($type, fn () => $this->cart->remove($this->owner($request), $id, $type), 'Item removed from cart');
    }

    /** DELETE /cart?type= */
    public function empty(Request $request): RedirectResponse
    {
        $type = $this->typeOf($request->input('type'));

        return $this->act($type, fn () => $this->cart->clear($this->owner($request), $type), 'Cart cleared');
    }

    /** POST /cart/coupon {coupon_code, type} */
    public function applyCoupon(CartCouponRequest $request): RedirectResponse
    {
        return $this->act($request->type(), fn () => $this->cart->applyCoupon($this->owner($request), $request->validated('coupon_code'), $request->type()), '🎉 Coupon applied');
    }

    /** DELETE /cart/coupon?type= */
    public function removeCoupon(Request $request): RedirectResponse
    {
        $type = $this->typeOf($request->input('type'));

        return $this->act($type, fn () => $this->cart->removeCoupon($this->owner($request), $type), 'Coupon removed');
    }

    /** Run a cart change and come back to the same cart: the refusal (out of stock, bad coupon, ...) is shown as a toast. */
    private function act(string $type, callable $change, ?string $done): RedirectResponse
    {
        try {
            $change();
        } catch (ApiException $e) {
            $this->flash('error', $this->messageOf($e));

            return redirect()->route('cart', ['type' => $type]);
        }

        if ($done) {
            $this->flash('success', $done);
        }

        return redirect()->route('cart', ['type' => $type]);
    }

    private function messageOf(ApiException $e): string
    {
        $m = $e->getMessage();

        return \Illuminate\Support\Facades\Lang::has($m) ? __($m) : $m;
    }

    private function typeOf(mixed $type): string
    {
        return $type === '30_min' ? '30_min' : 'normal';
    }

    private function owner(Request $request): CartOwner
    {
        return $this->guest->owner($request->user());
    }
}
