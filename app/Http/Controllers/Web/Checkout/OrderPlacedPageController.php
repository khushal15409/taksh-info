<?php

namespace App\Http\Controllers\Web\Checkout;

use App\Http\Controllers\Web\WebController;
use App\Services\Orders\OrderConfirmationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** "Order placed": what was ordered and where payment stands - read from our own records, only for the customer's own orders. */
class OrderPlacedPageController extends WebController
{
    public function __construct(private readonly OrderConfirmationService $confirmation) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $orderId = max(0, (int) $request->query('order'));
        $justPlaced = array_map('intval', (array) session(CheckoutPageController::SESSION_PLACED, []));

        // no order to talk about: the orders list
        if (! $orderId && ! $justPlaced) {
            return redirect()->route('profile', ['tab' => 'orders']);
        }

        $orderId = $orderId ?: end($justPlaced);
        $state = $this->confirmation->state($user, $orderId);
        if (! $state) {
            return $this->redirectWith('profile', 'error', 'We could not find that order', ['tab' => 'orders']);
        }

        // when several shops' orders were placed together, all of them are listed; otherwise just this one
        $ids = in_array($orderId, $justPlaced, true) ? $justPlaced : [$orderId];

        return $this->page('checkout.placed', ['state' => $state, 'orders' => $this->confirmation->summaries($user, $ids), 'orderId' => $orderId]);
    }
}
