<?php

namespace App\Services\Orders;

use App\Exceptions\CartProblemException;
use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Logistics\ShipmentStatusSync;
use App\Services\OrderStockService;

/**
 * Cancel an order as taksh-admin's OrderController::cancel does: the order is locked first, only a pending or confirmed order
 * can be cancelled, the stock goes back (a reservation if never paid, sold stock if paid), the coupon is released and a paid
 * payment is refunded through the gateway (when RAZORPAY_REFUND_ON_CANCEL is on) or flagged for a manual refund.
 */
class OrderCancelService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderStockService $stock,
        private readonly OrderRefundService $refunds,
        private readonly OrderNotifier $notifier,
        private readonly ShipmentStatusSync $shipments,
    ) {}

    /** @return array{order_id: int, order_number: string, order_status: string, refund_status: string} */
    public function cancel(User $user, int $orderId): array
    {
        $paid = null;
        $refundable = null;

        $order = $this->orders->transaction(function () use ($user, $orderId, &$paid, &$refundable) {
            $order = $this->orders->lockedOwned($user->id, $orderId) ?? throw new CartProblemException('api.order_not_found', 404);

            if ($order->order_status === 'cancelled') {
                throw new CartProblemException('api.order_already_cancelled', 400);
            }
            if (! in_array($order->order_status, ['pending', 'confirmed'], true)) {
                throw new CartProblemException('api.order_cannot_be_cancelled', 400);
            }

            $this->stock->release($order);
            $this->orders->setStatus($order, 'cancelled');
            $this->orders->releaseCoupon($order->id);

            // a paid payment is NOT marked refunded here: nothing has been refunded yet
            $refundable = $this->orders->refundablePayment($order);
            $paid = $this->orders->paidPayment($order);

            // flag it whenever no automatic refund will follow (feature off, or no gateway payment id)
            if ($paid && (! $this->refunds->enabled() || ! $refundable)) {
                $this->refunds->flagRefundRequired($paid);
            }

            return $order;
        });

        // the follow-ups after the status change (what the observer does in taksh-admin); none of them can undo the cancel
        $this->shipments->cancelled($order);
        $this->notifier->cancelled($order);

        // the gateway call is outside the transaction; it never changes the cancel result
        $refundStatus = OrderRefundService::NOT_APPLICABLE;
        if ($paid) {
            $refundStatus = OrderRefundService::MANUAL_REQUIRED;

            if ($this->refunds->enabled() && $refundable) {
                try {
                    $refundStatus = $this->refunds->refund($refundable, $order);
                } catch (\Throwable $e) {
                    // the order IS cancelled; a refund problem must not turn that into an error
                    report($e);
                    $refundStatus = OrderRefundService::FAILED;
                }
            }
        }

        return ['order_id' => $order->id, 'order_number' => $order->order_number, 'order_status' => $order->order_status, 'refund_status' => $refundStatus];
    }
}
