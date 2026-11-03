<?php

namespace App\Http\Controllers\Web\Orders;

use App\Exceptions\ApiException;
use App\Http\Controllers\Web\WebController;
use App\Services\Orders\OrderCancelService;
use App\Services\Orders\OrderPageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/** One order: /tracking?id= - status, address, bill, items, invoice, cancel, return and (Quick orders) the delivery person on a map. */
class OrderPageController extends WebController
{
    public function __construct(
        private readonly OrderPageService $orders,
        private readonly OrderCancelService $cancellation,
    ) {}

    public function show(Request $request): View|RedirectResponse|Response
    {
        $user = $request->user();
        if (! $user) {
            return $this->page('orders.show', ['order' => null, 'guest' => true]);
        }

        $id = max(0, (int) $request->query('id'));
        if (! $id) {
            return redirect()->route('profile', ['tab' => 'orders']);
        }

        $order = $this->orders->detail($user, $id);

        return $order
            ? $this->page('orders.show', ['order' => $order, 'guest' => false])
            : $this->notFound('orders.show', ['order' => null, 'guest' => false]);
    }

    /** POST /tracking/{id}/cancel - cancels an order that has not shipped; a paid one is refunded as taksh-admin does it */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        try {
            $r = $this->cancellation->cancel($request->user(), $id);
        } catch (ApiException $e) {
            return $this->redirectWith('tracking', 'error', Lang::has($e->getMessage()) ? __($e->getMessage()) : $e->getMessage(), ['id' => $id]);
        } catch (\Throwable $e) {
            report($e);

            return $this->redirectWith('tracking', 'error', 'We could not cancel the order. Please try again.', ['id' => $id]);
        }

        return $this->redirectWith('tracking', 'success', match ($r['refund_status'] ?? null) {
            'processed' => 'Order cancelled. Your refund has been processed.',
            'pending' => 'Order cancelled. Your refund is on its way.',
            'not_applicable' => 'Order cancelled',
            default => 'Order cancelled. We\'ll refund your payment shortly.',
        }, ['id' => $id]);
    }
}
