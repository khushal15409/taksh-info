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
 * "Review & Place Order" (Standard, or Quick with ?type=30_min). The page shows the saved addresses, the ways to pay the
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
        private readonly \App\Services\Quick\QuickServiceability $quick,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $buy = $request->filled('buy') ? ['variant_id' => (int) $request->query('buy'), 'qty' => max(1, (int) $request->query('qty', 1))] : null;

        if ($buy) {
            // Buy Now: the bill of this one product; the cart is not involved
            try {
                ['type' => $type, 'cart' => $cart] = $this->cart->buyNow($user->id, $buy['variant_id'], $buy['qty']);
            } catch (ApiException $e) {
                return $this->redirectWith('home', 'error', Lang::has($e->getMessage()) ? __($e->getMessage()) : $e->getMessage());
            }
            $buy['qty'] = $cart['items'][0]['qty'];
        } else {
            $type = $request->query('type') === '30_min' ? '30_min' : 'normal';
            $cart = $this->cart->get($this->cartOwner($request), $type)['cart'];
        }
        $quick = $type === '30_min';

        if (! $cart['items']) {
            return $this->redirectWith('cart', 'error', 'Your cart is empty - add something to check out', ['type' => $type]);
        }

        $book = $this->addresses->list($user->id);
        $all = collect([$book['default_address'], ...$book['addresses']])->filter()->values()->all();
        $options = array_filter(PaymentOptions::all()[$quick ? 'quick' : 'standard']);

        // Quick goes only to an address the shops of this cart serve (10 km, or the shop's pincode): the others are shown but cannot be picked
        $serviceable = [];
        if ($quick) {
            $vendorIds = collect($cart['items'])->pluck('vendor_id')->filter()->unique()->values()->all();
            foreach ($all as $a) {
                $serviceable[$a['id']] = $this->quick->servesAll($vendorIds, $a['latitude'] ?? null, $a['longitude'] ?? null, $a['pincode'] ?? null);
            }
        }
        $usable = fn ($a) => ! $quick || ($serviceable[$a['id']] ?? false);
        $first = collect([$book['default_address'], ...$all])->filter()->first($usable);
        // after adding / editing an address the page returns with ?address= so the customer keeps the one they were on
        $asked = collect($all)->first(fn ($a) => (int) $a['id'] === (int) $request->query('address'));
        if ($asked && $usable($asked)) {
            $first = $asked;
        }

        return $this->page('checkout.index', [
            'type' => $type,
            'buy' => $buy,
            'quick' => $quick,
            'serviceable' => $serviceable,
            'cart' => $cart,
            'addresses' => $all,
            'selectedAddress' => old('address_id', $first['id'] ?? null),
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

        $buy = $request->buyNow();
        if ($buy) {
            // Buy Now: back to this checkout (not the cart) when something is wrong, and the type comes from the product itself
            try {
                ['type' => $type, 'cart' => $cart] = $this->cart->buyNow($user->id, $buy['variant_id'], $buy['qty']);
            } catch (ApiException $e) {
                return $this->redirectWith('home', 'error', Lang::has($e->getMessage()) ? __($e->getMessage()) : $e->getMessage());
            }
            if (collect($cart['items'])->contains(fn ($i) => ! $i['in_stock'])) {
                return $this->redirectWith('home', 'error', 'This product is not available right now.');
            }
        } else {
            $type = $request->type();
            $cart = $this->cart->get($this->cartOwner($request), $type)['cart'];
            if (collect($cart['items'])->contains(fn ($i) => ! $i['in_stock'])) {
                return $this->redirectWith('cart', 'error', 'Some items in your cart are unavailable. Please review your cart.', ['type' => $type]);
            }
        }
        $back = $buy ? ['buy' => $buy['variant_id'], 'qty' => $buy['qty']] : ($type === '30_min' ? ['type' => $type] : []);

        try {
            $result = $this->idempotency->run($user, $v['checkout_token'], function () use ($user, $v, $type, $buy) {
                $placed = $this->placement->place($user, ['address_id' => (int) $v['address_id'], 'delivery_type' => $type, 'payment_method' => $v['payment_method']] + ($buy ? ['buy_now' => $buy] : []));

                return collect($placed['placed'])->pluck('id')->map(fn ($id) => (int) $id)->all();
            });
        } catch (ApiException $e) {
            // not placed (empty cart, no stock, address not found, method switched off ...): nothing was charged and the cart is as it was
            $this->flash('error', Lang::has($e->getMessage()) ? __($e->getMessage()) : $e->getMessage());

            return $e->getMessage() === 'api.cart_empty' && ! $buy ? redirect()->route('cart', $type === '30_min' ? ['type' => $type] : []) : redirect()->route('checkout', $back);
        } catch (\Throwable $e) {
            report($e);
            Log::error('Order placement failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $this->flash('error', 'We could not place your order. Please try again.');

            return redirect()->route('checkout', $back);
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
