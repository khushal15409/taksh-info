<?php

namespace App\Services\Orders;

use App\Exceptions\CartProblemException;
use App\Models\User;
use App\Repositories\Contracts\CheckoutRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\PaymentService;

/**
 * What the "order placed", "pay for your order" and "payment result" pages tell the customer: the truth from our own records
 * (never from the address bar), and only for the customer's own orders. Where payment stands is PaymentService::status(), the same answer
 * as GET /payments/status/{id}.
 */
class OrderConfirmationService
{
    /** An online order is waiting for its payment in these states. */
    private const PAYABLE_ORDER_STATUSES = ['pending', 'pending_payment'];

    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentRepositoryInterface $paymentRepo,
        private readonly CheckoutRepositoryInterface $checkout,
    ) {}

    /**
     * The state of one order for the customer, or null when it is not theirs / does not exist.
     *
     * @return array{id: int, payment_status: string, order_status: string, method: ?string, amount: ?float, paid: bool, cod: bool, unpaid_online: bool, can_pay: bool}|null
     */
    public function state(User $user, int $orderId): ?array
    {
        try {
            $s = $this->payments->status($user, $orderId);
        } catch (CartProblemException) {
            return null;
        }

        $method = $s['payment']['method'] ?? null;
        $paid = $s['payment_status'] === 'paid';
        $cod = $method === 'cod';

        return [
            'id' => $orderId,
            'payment_status' => $s['payment_status'],
            'order_status' => $s['order_status'],
            'method' => $method,
            'amount' => isset($s['payment']['amount']) ? $s['payment']['amount'] / 100 : null,   // paise -> rupees
            'paid' => $paid,
            'cod' => $cod,
            'unpaid_online' => ! $paid && ! $cod && in_array($s['payment_status'], ['pending', 'failed'], true),
            // the order can still be paid for: not paid, not cash on delivery, still waiting for payment
            'can_pay' => ! $paid && ! $cod && in_array($s['order_status'], self::PAYABLE_ORDER_STATUSES, true),
        ];
    }

    /**
     * Short summaries (number, shop, items, total) of the customer's own orders among $ids, in the given order.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function summaries(User $user, array $ids): array
    {
        $rows = [];
        foreach (array_unique($ids) as $id) {
            $order = $this->paymentRepo->ownedOrder($user->id, (int) $id);
            if ($order) {
                $rows[] = $this->checkout->summary($order);
            }
        }

        return $rows;
    }
}
