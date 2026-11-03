<?php

namespace App\Http\Controllers\Web\Checkout;

use App\Http\Controllers\Web\WebController;
use App\Services\Orders\OrderConfirmationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * "Payment successful / failed". The address bar only says where we came from (?status=&order=&reason=): success is shown only when our
 * own records say the customer's order is paid, so a hand-typed "status=success" can never claim a payment that did not happen.
 */
class PaymentResultPageController extends WebController
{
    public function __construct(private readonly OrderConfirmationService $confirmation) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $orderId = max(0, (int) $request->query('order'));
        $state = $user && $orderId ? $this->confirmation->state($user, $orderId) : null;

        $outcome = match (true) {
            $state && $state['paid'] => 'success',
            $request->query('status') === 'failed' || $state => 'failed',
            default => 'unknown',   // nothing we can check (not signed in, or not their order)
        };

        return $this->page('checkout.result', [
            'outcome' => $outcome,
            'orderId' => $orderId ?: null,
            'state' => $state,
            'reason' => $outcome === 'failed' ? mb_substr(trim((string) $request->query('reason')), 0, 300) : '',
        ]);
    }
}
