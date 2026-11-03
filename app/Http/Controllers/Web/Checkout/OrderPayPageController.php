<?php

namespace App\Http\Controllers\Web\Checkout;

use App\Http\Controllers\Web\WebController;
use App\Services\Orders\OrderConfirmationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * "Pay for your order": an unpaid online order. The page says what is payable; only the Razorpay window itself is in JS (modules/pay.js),
 * which creates the Razorpay order and verifies the result through /payments/create and /payments/verify - the server alone decides that
 * an order is paid. ?start=1 (coming from checkout) opens the window straight away.
 */
class OrderPayPageController extends WebController
{
    public function __construct(private readonly OrderConfirmationService $confirmation) {}

    public function show(Request $request): View
    {
        $orderId = max(0, (int) $request->query('order_id'));
        $state = $orderId ? $this->confirmation->state($request->user(), $orderId) : null;

        return $this->page('checkout.pay', [
            'orderId' => $orderId,
            'state' => $state,
            'start' => $state && $state['can_pay'] && $request->boolean('start'),
        ]);
    }
}
