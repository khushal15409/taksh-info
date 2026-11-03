<?php

namespace App\Services;

use App\Exceptions\CartProblemException;
use App\Helpers\Money;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Online payment of an order through Razorpay, as taksh-admin's PaymentController does it: create a Razorpay order
 * for the amount fixed when the order was placed (orders placed together are paid by one Razorpay order), then verify
 * the browser's signature AND ask Razorpay about the payment before the order is settled as paid.
 */
class PaymentService
{
    private const PAYABLE = ['pending_payment', 'pending'];

    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly RazorpayGateway $gateway,
        private readonly PaymentSettlementService $settlement,
    ) {}

    /**
     * Create (or reuse) the Razorpay order for an order the customer owns.
     *
     * @return array{data: array<string, mixed>, message: string}
     */
    public function create(User $user, int $orderId): array
    {
        $order = $this->payments->ownedOrder($user->id, $orderId) ?? throw new CartProblemException('api.order_not_found', 404);

        if (! in_array($order->order_status, self::PAYABLE, true)) {
            throw new CartProblemException('Order is not in a valid state for payment', 400);
        }

        // the order's online payment record, created when the order was placed (gateway attempts add rows after it)
        $online = $this->payments->onlinePayment($order->id) ?? throw new CartProblemException('Payment method is not online', 400);

        if ($this->payments->isPaid($order->id)) {
            throw new CartProblemException('Order is already paid', 400);
        }

        // orders placed together (one per vendor) are paid by one Razorpay order
        if ($this->payments->hasCheckoutGroupColumn() && $order->checkout_group_id) {
            return $this->createGroup($order, $user->id);
        }

        // a Razorpay order still open for this order is returned again (retry, double tap) instead of creating another one
        if ($open = $this->payments->openRazorpayPayment($order->id)) {
            return ['data' => $this->payload($open->razorpay_order_id, $open->amount, $open->currency), 'message' => 'Razorpay order already exists'];
        }

        // the amount to pay is the one fixed when the order was placed; a later change to the total must never be charged silently
        $amount = (int) $online->amount;
        if ($amount <= 0) {
            $amount = Money::toMinorUnits($order->grand_total);
        } elseif ((float) $order->grand_total > 0 && Money::toMinorUnits($order->grand_total) !== $amount) {
            Log::error('Order total and payment amount differ; payment not created', ['order_id' => $order->id, 'payment_amount' => $amount, 'order_total' => $order->grand_total]);

            throw new CartProblemException('Order amount mismatch', 409);
        }

        try {
            // the gateway is called before any transaction, so no lock is held while waiting for it
            $razorpayOrder = $this->gateway->createOrder($amount, 'INR', ['order_id' => $order->id, 'order_number' => $order->order_number]);

            $payment = $this->payments->transaction(function () use ($order, $razorpayOrder, $amount) {
                // serialise concurrent attempts for one order; if another request already stored a Razorpay order, use it
                $this->payments->lockOrders([$order->id]);

                return $this->payments->openRazorpayPayment($order->id) ?? $this->payments->createPayment([
                    'order_id' => $order->id, 'razorpay_order_id' => $razorpayOrder['id'], 'gateway' => 'razorpay', 'amount' => $amount,
                    'currency' => 'INR', 'status' => 'initiated', 'method' => 'online', 'raw_response' => $razorpayOrder,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to create Razorpay order', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            throw new CartProblemException('Failed to create payment order', 500);
        }

        return ['data' => $this->payload($payment->razorpay_order_id, $payment->amount, $payment->currency), 'message' => 'Razorpay order created successfully'];
    }

    /**
     * One Razorpay order for every still-payable order of a checkout group. Each order keeps its own payment row and
     * amount (so refunds stay per order); the Razorpay order is for their sum.
     */
    private function createGroup(Order $requested, int $userId): array
    {
        /** @var array<int, int> $amounts order id => paise */
        $amounts = [];

        foreach ($this->payments->groupOrders($requested->checkout_group_id, $userId) as $groupOrder) {
            if (! in_array($groupOrder->order_status, self::PAYABLE, true)) {
                continue;
            }

            $online = $this->payments->onlinePayment($groupOrder->id);
            if (! $online || $this->payments->isPaid($groupOrder->id)) {
                continue;
            }

            $amount = (int) $online->amount;
            if ($amount > 0 && (float) $groupOrder->grand_total > 0 && Money::toMinorUnits($groupOrder->grand_total) !== $amount) {
                Log::error('Order total and payment amount differ; payment not created', ['order_id' => $groupOrder->id]);

                throw new CartProblemException('Order amount mismatch', 409);
            }

            $amounts[$groupOrder->id] = $amount > 0 ? $amount : Money::toMinorUnits($groupOrder->grand_total);
        }

        if ($amounts === []) {
            throw new CartProblemException('Order is not in a valid state for payment', 400);
        }

        $total = array_sum($amounts);
        $message = 'Razorpay order created successfully';

        if ($open = $this->openGroupRazorpayOrder($amounts)) {
            return ['data' => $this->payload($open, $total), 'message' => $message];
        }

        $orderIds = array_keys($amounts);

        try {
            $razorpayOrder = $this->gateway->createOrder($total, 'INR', ['order_id' => $orderIds[0], 'order_ids' => implode(',', $orderIds)]);

            $razorpayOrderId = $this->payments->transaction(function () use ($amounts, $orderIds, $razorpayOrder) {
                $this->payments->lockOrders($orderIds);

                if ($open = $this->openGroupRazorpayOrder($amounts)) {
                    return $open;
                }

                foreach ($amounts as $orderId => $amount) {
                    $this->payments->createPayment([
                        'order_id' => $orderId, 'razorpay_order_id' => $razorpayOrder['id'], 'gateway' => 'razorpay', 'amount' => $amount,
                        'currency' => 'INR', 'status' => 'initiated', 'method' => 'online', 'raw_response' => $razorpayOrder,
                    ]);
                }

                return $razorpayOrder['id'];
            });
        } catch (\Throwable $e) {
            Log::error('Failed to create Razorpay order for a checkout group', ['order_ids' => $orderIds, 'error' => $e->getMessage()]);

            throw new CartProblemException('Failed to create payment order', 500);
        }

        return ['data' => $this->payload($razorpayOrderId, $total), 'message' => $message];
    }

    /** The open Razorpay order that covers exactly these orders and amounts, if there is one. @param array<int, int> $amounts */
    private function openGroupRazorpayOrder(array $amounts): ?string
    {
        foreach ($this->payments->openPaymentsByRazorpayOrder(array_keys($amounts)) as $razorpayOrderId => $rows) {
            $covers = collect($rows)->mapWithKeys(fn (Payment $row) => [$row->order_id => (int) $row->amount])->all();
            ksort($covers);
            $expected = $amounts;
            ksort($expected);

            if ($covers === $expected) {
                return (string) $razorpayOrderId;
            }
        }

        return null;
    }

    private function payload(string $razorpayOrderId, int $amount, ?string $currency = 'INR'): array
    {
        return ['razorpay_order_id' => $razorpayOrderId, 'amount' => $amount, 'currency' => $currency ?? 'INR', 'razorpay_key' => $this->gateway->keyId()];
    }

    /**
     * Verify what the browser got back from Razorpay and settle the order as paid.
     *
     * @return array{data: array<string, mixed>, message: string}
     */
    public function verify(User $user, string $razorpayOrderId, string $razorpayPaymentId, string $signature): array
    {
        // the payment is found from OUR records by the gateway order id; a client's order id is never proof of anything
        $payment = $this->payments->findByRazorpayOrder($razorpayOrderId);

        if (! $payment || ! $payment->order) {
            throw new CartProblemException('Payment not found', 404);
        }
        if ((int) $payment->order->user_id !== $user->id) {
            throw new CartProblemException('Unauthorized', 403);
        }

        /** @var Collection<int, Payment> $group orders paid together share this Razorpay order; empty for a single order */
        $group = $this->payments->groupPayments($payment);
        if ($group->isNotEmpty() && $this->payments->anyOrderOfOtherUser($group->pluck('order_id')->all(), $user->id)) {
            throw new CartProblemException('Unauthorized', 403);
        }

        if (! $this->gateway->verifySignature($razorpayOrderId, $razorpayPaymentId, $signature)) {
            // a bad signature proves nothing; markFailed only touches a payment that is still open, so a paid one is never changed
            try {
                foreach ($group->isEmpty() ? [$payment] : $group as $groupPayment) {
                    $this->settlement->markFailed($groupPayment->id, ['verification_failed' => true, 'attempted_at' => now()->toDateTimeString()]);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to update payment status on verification failure', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }

            throw new CartProblemException('Payment verification failed', 400);
        }

        // idempotency: already processed
        if ($payment->isPaid() && $group->every(fn (Payment $p) => $p->isPaid())) {
            return ['data' => $this->verified($payment, $payment->order->order_status, 'Payment already verified'), 'message' => 'Payment already verified'];
        }

        // the gateway lookup happens BEFORE any lock or transaction, so a slow gateway never holds database locks
        $remote = $this->gateway->fetchPayment($razorpayPaymentId);

        if (! is_array($remote)) {
            Log::warning('Razorpay payment lookup failed during verify', ['payment_id' => $payment->id]);

            throw new CartProblemException('Unable to confirm the payment right now. Please try again shortly.', 503);
        }

        // defence in depth on top of the signature: the payment must be for this Razorpay order, our amount and currency, and captured/authorised
        $mismatch = ($remote['order_id'] ?? null) !== $payment->razorpay_order_id
            || (int) ($remote['amount'] ?? -1) !== (int) ($group->isEmpty() ? $payment->amount : $group->sum('amount'))
            || strtoupper((string) ($remote['currency'] ?? '')) !== strtoupper((string) ($payment->currency ?? 'INR'))
            || ! in_array($remote['status'] ?? null, ['captured', 'authorized'], true);

        if ($mismatch) {
            Log::warning('Razorpay payment does not match our payment record', ['payment_id' => $payment->id]);

            throw new CartProblemException('Payment verification failed', 400);
        }

        $attributes = ['razorpay_payment_id' => $razorpayPaymentId, 'method' => $remote['method'] ?? null];
        $extra = ['razorpay_payment' => $remote, 'verified_at' => now()->toDateTimeString()];

        $result = $group->isEmpty()
            ? $this->settlement->markPaid($payment->id, $attributes, $extra)
            : $this->settlement->markGroupPaid($group, $payment, $attributes, $extra);

        if ($result['outcome'] === PaymentSettlementService::PAID_AFTER_CANCEL) {
            // the payment is recorded, but a cancelled order is never revived
            throw new CartProblemException('This order was cancelled, so it was not confirmed. The payment will be refunded.', 409);
        }
        if ($result['outcome'] === PaymentSettlementService::IGNORED) {
            throw new CartProblemException('Payment can no longer be verified', 409);
        }

        $message = $result['outcome'] === PaymentSettlementService::ALREADY_PAID ? 'Payment already verified' : 'Payment verified successfully';

        return ['data' => $this->verified($payment, $result['order']->order_status ?? $payment->order->order_status), 'message' => $message];
    }

    private function verified(Payment $payment, ?string $orderStatus, ?string $message = null): array
    {
        return array_filter([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'status' => $message ? $payment->status : 'paid',
            'order_status' => $orderStatus,
            'message' => $message,
        ], fn ($v) => $v !== null);
    }

    /** @return array<string, mixed> */
    public function status(User $user, int $orderId): array
    {
        $order = $this->payments->orderWithPayments($user->id, $orderId) ?? throw new CartProblemException('Order not found', 404);
        $latest = $order->latestPayment;

        $map = ['paid' => 'paid', 'success' => 'paid', 'failed' => 'failed', 'refunded' => 'refunded', 'initiated' => 'pending', 'pending' => 'pending'];

        return [
            'payment_status' => $latest ? ($map[$latest->status] ?? $latest->status) : 'pending',
            'order_status' => $order->order_status,
            'payment' => $latest ? [
                'id' => $latest->id, 'razorpay_order_id' => $latest->razorpay_order_id, 'razorpay_payment_id' => $latest->razorpay_payment_id,
                'status' => $latest->status, 'amount' => $latest->amount, 'currency' => $latest->currency, 'method' => $latest->method,
            ] : null,
        ];
    }
}
