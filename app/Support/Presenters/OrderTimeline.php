<?php

namespace App\Support\Presenters;

/**
 * The customer-friendly steps over the order_status values the rest of Taksh moves an order through (Placed -> Confirmed -> Packed ->
 * Shipped -> Out for delivery -> Delivered), and how far a given order has got. A cancelled / failed / undelivered order shows just
 * "Order Placed" and what happened.
 */
final class OrderTimeline
{
    public const STEPS = [
        ['Order Placed', 'We received your order', ['pending', 'pending_payment']],
        ['Confirmed', 'The shop accepted your order', ['confirmed']],
        ['Packed', 'Your items are being packed', ['accepted', 'ready_to_pickup', 'pickup_completed', 'fm_pending', 'delivered_to_fm_center']],
        ['Shipped', 'Your order is on its way', ['shipped', 'in_transit']],
        ['Out for Delivery', 'The delivery partner is nearby', ['out_for_delivery']],
        ['Delivered', 'Delivered. Enjoy!', ['delivered']],
    ];

    public const TERMINAL = ['cancelled', 'failed', 'undelivered'];

    /** Orders can be cancelled until they are shipped. */
    public const CANCELLABLE = ['pending', 'confirmed'];

    public static function stepOf(string $status): int
    {
        foreach (self::STEPS as $i => $step) {
            if (in_array($status, $step[2], true)) {
                return $i;
            }
        }

        return -1;
    }

    /** @return array{terminal: bool, steps: list<array{title: string, text: ?string, state: string}>} state: done | current | todo | bad */
    public static function build(string $status): array
    {
        $idx = self::stepOf($status);

        if (in_array($status, self::TERMINAL, true) || $idx < 0) {
            $terminal = in_array($status, self::TERMINAL, true);

            return ['terminal' => true, 'steps' => [
                ['title' => 'Order Placed', 'text' => null, 'state' => 'done'],
                ['title' => ucwords(str_replace('_', ' ', $status)), 'text' => null, 'state' => $terminal ? 'bad' : 'current'],
            ]];
        }

        $steps = [];
        foreach (self::STEPS as $i => [$title, $text]) {
            $steps[] = ['title' => $title, 'text' => $text, 'state' => $i < $idx || ($i === $idx && $idx === count(self::STEPS) - 1) ? 'done' : ($i === $idx ? 'current' : 'todo')];
        }

        return ['terminal' => false, 'steps' => $steps];
    }
}
