<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Orders\OrderNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * The single place where a customer order payment becomes paid or failed (as in taksh-admin, where the app's verify call
 * and the Razorpay webhook share it): rows are locked for the whole change, an already paid payment is never changed again
 * (no second stock deduction, no downgrade by a late event) and a cancelled order is never revived by a payment.
 */
class PaymentSettlementService
{
    public const PAID = 'paid';
    public const ALREADY_PAID = 'already_paid';
    public const PAID_AFTER_CANCEL = 'paid_after_cancel';
    public const IGNORED = 'ignored';

    /** Payment states a payment event may still move to paid. */
    private const SETTLEABLE = ['initiated', 'pending', 'failed'];

    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly OrderStockService $stock,
        private readonly OrderNotifier $notifier,
        private readonly \App\Services\Quick\QuickOrderDispatch $dispatch,
    ) {}

    /**
     * Settle every payment of a group as paid, each in its own transaction, so a repeat only finishes what is left.
     *
     * @param  Collection<int, Payment>  $group
     * @return array{outcome: string, payment: Payment, order: ?Order}
     */
    public function markGroupPaid(Collection $group, Payment $primary, array $attributes, array $rawExtra): array
    {
        $results = $group->map(fn (Payment $p) => $this->markPaid($p->id, $attributes, $rawExtra));

        return $results->first(fn ($r) => in_array($r['outcome'], [self::PAID, self::ALREADY_PAID], true))
            ?? $results->first(fn ($r) => $r['payment']->id === $primary->id)
            ?? $results->first();
    }

    /** @return array{outcome: string, payment: Payment, order: ?Order} */
    public function markPaid(int $paymentId, array $attributes, array $rawExtra): array
    {
        $result = $this->payments->transaction(function () use ($paymentId, $attributes, $rawExtra) {
            [$order, $payment] = $this->payments->lockedOrderAndPayment($paymentId);

            if ($payment->isPaid()) {
                return ['outcome' => self::ALREADY_PAID, 'payment' => $payment, 'order' => $order];
            }

            if (! in_array($payment->status, self::SETTLEABLE, true)) {
                // e.g. refunded: a gateway event must not reopen it
                Log::warning('Payment event ignored for payment in a final state', ['payment_id' => $payment->id, 'status' => $payment->status]);

                return ['outcome' => self::IGNORED, 'payment' => $payment, 'order' => $order];
            }

            $cancelled = $order && $order->order_status === 'cancelled';

            $this->payments->updatePayment($payment, array_merge($attributes, [
                'status' => 'paid',
                'raw_response' => array_merge($payment->raw_response ?? [], $rawExtra, $cancelled ? ['paid_after_cancel' => true, 'needs_refund' => true] : []),
            ]));

            if ($cancelled) {
                // money was taken for an order that is already cancelled: keep it cancelled and leave a trail for a refund
                Log::warning('Payment received for a cancelled order; order left cancelled, refund needed', ['payment_id' => $payment->id, 'order_id' => $order->id]);

                return ['outcome' => self::PAID_AFTER_CANCEL, 'payment' => $payment, 'order' => $order];
            }

            if ($order) {
                if (in_array($order->order_status, ['pending', 'pending_payment'], true)) {
                    $this->payments->updateOrder($order, ['order_status' => 'confirmed', 'confirmed_at' => now()]);
                }

                $this->stock->commit($order);
            }

            return ['outcome' => self::PAID, 'payment' => $payment, 'order' => $order];
        });

        if ($result['outcome'] === self::PAID && $result['order']) {
            $this->notifier->paymentConfirmed($result['order']);   // after the transaction; never throws into the payment

            // a paid Quick order now enters rider dispatch; a dispatch problem must never undo or block the payment
            try {
                $order = $result['order'];
                if (\App\Services\Quick\QuickOrderDispatch::isQuick($order->delivery_type, (bool) $order->is_express)) {
                    $this->dispatch->logPlaced($order);
                    $this->dispatch->tryAssignRider($order);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $result;
    }

    /** Mark a payment failed - only when it is still open. Returns whether anything changed. */
    public function markFailed(int $paymentId, array $rawExtra, array $attributes = []): bool
    {
        return $this->payments->transaction(function () use ($paymentId, $rawExtra, $attributes) {
            [, $payment] = $this->payments->lockedOrderAndPayment($paymentId);

            if (! in_array($payment->status, ['initiated', 'pending'], true)) {
                return false;
            }

            $this->payments->updatePayment($payment, array_merge($attributes, [
                'status' => 'failed',
                'raw_response' => array_merge($payment->raw_response ?? [], $rawExtra),
            ]));

            return true;
        });
    }
}
