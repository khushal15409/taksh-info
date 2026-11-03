<?php

namespace App\Http\Controllers\Web\Checkout;

use App\Exceptions\ApiException;
use App\Http\Controllers\Web\WebController;
use App\Http\Requests\Web\Checkout\PlaceOrderPageRequest;
use App\Services\AddressService;
use App\Services\CartService;
use App\Services\Orders\CheckoutIdempotency;
use App\Services\Orders\OrderPlacementService;
use App\Support\PaymentOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "Review & Place Order" (standard delivery; Quick orders are placed in the app). The page shows the saved addresses, the ways to pay the
 * admin has switched on, and the server-built bill. Confirming places the order through the same OrderPlacementService as the JSON
 * endpoint - stock is reserved under locks there - and is safe to press twice (CheckoutIdempotency). Cash on delivery goes straight to
 * "order placed"; an online order exists unpaid and continues on the pay page, where the Razorpay window opens.
 */
class CheckoutPageController extends WebController
{
    public const SESSION_PLACED = 'checkout.last_placed';

    public function __construct(
        private readonly CartService $cart,
        private readonly AddressService $addresses,
        private readonly OrderPlacementService $placement,
        private readonly CheckoutIdempotency $idempotency,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $cart = $this->cart->get($this->cartOwner($request), 'normal')['cart'];

        if (! $cart['items']) {
            return $this->redirectWith('cart', 'error', 'Your cart is empty - add something to check out', ['type' => 'normal']);
        }

        $book = $this->addresses->list($user->id);
        $all = collect([$book['default_address'], ...$book['addresses']])->filter()->values()->all();
        $options = array_filter(PaymentOptions::all()['standard']);

        return $this->page('checkout.index', [
            'cart' => $cart,
            'addresses' => $all,
            'selectedAddress' => old('address_id', $book['default_address']['id'] ?? ($all[0]['id'] ?? null)),
            'methods' => array_keys($options),
            'method' => old('payment_method', array_key_exists('cod', $options) ? 'cod' : (array_key_first($options) ?? 'online')),
            'unavailable' => collect($cart['items'])->contains(fn ($i) => ! $i['in_stock']),
            'token' => (string) Str::uuid(),
        ]);
    }

    public function place(PlaceOrderPageRequest $request): RedirectResponse
    {
        $user = $request->user();
        $v = $request->validated();

        $cart = $this->cart->get($this->cartOwner($request), 'normal')['cart'];
        if (collect($cart['items'])->contains(fn ($i) => ! $i['in_stock'])) {
            return $this->redirectWith('cart', 'error', 'Some items in your cart are unavailable. Please review your cart.', ['type' => 'normal']);
        }

        try {
            $result = $this->idempotency->run($user, $v['checkout_token'], function () use ($user, $v) {
                $placed = $this->placement->place($user, ['address_id' => (int) $v['address_id'], 'delivery_type' => 'normal', 'payment_method' => $v['payment_method']]);

                return collect($placed['placed'])->pluck('id')->map(fn ($id) => (int) $id)->all();
            });
        } catch (ApiException $e) {
            // not placed (empty cart, no stock, address not found, method switched off ...): nothing was charged and the cart is as it was
            $this->flash('error', Lang::has($e->getMessage()) ? __($e->getMessage()) : $e->getMessage());

            return redirect()->route($e->getMessage() === 'api.cart_empty' ? 'cart' : 'checkout');
        } catch (\Throwable $e) {
            report($e);
            Log::error('Order placement failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $this->flash('error', 'We could not place your order. Please try again.');

            return redirect()->route('checkout');
        }

        $ids = $result['order_ids'];
        $last = end($ids);
        session()->put(self::SESSION_PLACED, $ids);

        // an order that has to be paid online continues on the pay page; the Razorpay window opens there
        if ($v['payment_method'] === 'online') {
            return redirect()->route('order.pay', ['order_id' => $last, 'start' => 1]);
        }

        return $this->redirectWith('order.placed', 'success', '🎉 Order placed', ['order' => $last]);
    }

    private function cartOwner(Request $request): \App\Support\CartOwner
    {
        return app(\App\Services\GuestCartSession::class)->owner($request->user());
    }
}
