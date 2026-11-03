<?php

namespace App\Services\Shiprocket;

use Carbon\Carbon;

/**
 * Shiprocket status text -> our controlled shipment states, plus the rules that stop a late or
 * duplicate event from moving a shipment backwards. Independent of service_courier_bookings.status.
 *
 * Normalized states (service_courier_shipments.shipment_status):
 *   pending, order_created, awb_assigned, pickup_requested, picked_up, in_transit, out_for_delivery,
 *   delivered, exception, rto_initiated, rto_in_transit, rto_delivered, cancelled
 * (pending..pickup_requested are also written by the C7 shipment flow.)
 */
class ShiprocketStatus
{
    private const RANK = ['pending' => 0, 'order_created' => 1, 'awb_assigned' => 2, 'pickup_requested' => 3, 'picked_up' => 4, 'in_transit' => 5, 'out_for_delivery' => 6, 'delivered' => 9];

    public const TERMINAL = ['delivered', 'rto_delivered', 'cancelled'];

    private const LATERAL = ['exception', 'rto_initiated', 'rto_in_transit'];

    /** Normalized state for a Shiprocket status label, or null when we do not recognise it. */
    public static function normalize(?string $label): ?string
    {
        $s = strtoupper(trim(preg_replace('/[\s_\-]+/', ' ', (string) $label)));
        if ($s === '') {
            return null;
        }

        return match (true) {
            str_starts_with($s, 'RTO') && str_contains($s, 'DELIVERED') => 'rto_delivered',
            $s === 'RTO ACKNOWLEDGED' => 'rto_delivered',
            str_starts_with($s, 'RTO') && str_contains($s, 'TRANSIT') => 'rto_in_transit',
            str_starts_with($s, 'RTO') => 'rto_initiated',
            in_array($s, ['CANCELED', 'CANCELLED'], true) => 'cancelled',
            $s === 'DELIVERED' => 'delivered',
            $s === 'OUT FOR DELIVERY' => 'out_for_delivery',
            str_contains($s, 'IN TRANSIT') || str_contains($s, 'REACHED AT DESTINATION HUB') || $s === 'REACHED DESTINATION HUB' => 'in_transit',
            in_array($s, ['PICKED UP', 'SHIPPED', 'PICKUP DONE'], true) => 'picked_up',
            in_array($s, ['PICKUP SCHEDULED', 'PICKUP GENERATED', 'PICKUP QUEUED', 'PICKUP RESCHEDULED', 'OUT FOR PICKUP'], true) => 'pickup_requested',
            in_array($s, ['AWB ASSIGNED', 'READY TO SHIP', 'MANIFEST GENERATED', 'INVOICED'], true) => 'awb_assigned',
            in_array($s, ['NEW', 'ORDER PLACED'], true) => 'order_created',
            in_array($s, ['UNDELIVERED', 'DELIVERY ATTEMPTED', 'SHIPMENT DELAYED', 'PICKUP EXCEPTION', 'LOST', 'DAMAGED', 'DESTROYED', 'HOLD', 'MISROUTED'], true) => 'exception',
            default => null,
        };
    }

    private const LABELS = [
        'pending' => 'Pending', 'order_created' => 'Order Created', 'awb_assigned' => 'Courier Assigned', 'pickup_requested' => 'Pickup Scheduled',
        'picked_up' => 'Picked Up', 'in_transit' => 'In Transit', 'out_for_delivery' => 'Out for Delivery', 'delivered' => 'Delivered',
        'exception' => 'Delivery Exception', 'rto_initiated' => 'Return Initiated', 'rto_in_transit' => 'Return In Transit',
        'rto_delivered' => 'Returned to Sender', 'cancelled' => 'Cancelled',
    ];

    /** @return array<string, string> every normalized state and its wording (for admin filters) */
    public static function all(): array
    {
        return self::LABELS;
    }

    /** Customer-facing wording for a normalized state; null for null or an unknown state. */
    public static function label(?string $status): ?string
    {
        return self::LABELS[$status] ?? null;
    }

    public static function isTerminal(?string $status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }

    public static function rank(?string $status): int
    {
        return self::RANK[$status] ?? 0;
    }

    /**
     * Should an event with `$new` move a shipment that is currently `$current`?
     * $eventTime / $latestKnown: this event's time and the newest time already stored (either may be null).
     */
    public static function shouldApply(?string $current, ?string $new, ?Carbon $eventTime, ?Carbon $latestKnown): bool
    {
        if ($new === null || $new === $current || self::isTerminal($current)) {
            return false;
        }
        $olderThanKnown = $eventTime && $latestKnown && $eventTime->lt($latestKnown);

        if (in_array($new, self::LATERAL, true) || in_array($new, self::TERMINAL, true)) {
            // A delayed exception/RTO report must not override something that happened later.
            return ! ($olderThanKnown && ! self::isTerminal($new));
        }
        if (str_starts_with((string) $current, 'rto_')) {
            return false; // a return is never turned back into a forward delivery
        }
        if ($current === 'exception') {
            return ! $olderThanKnown;
        }

        return self::rank($new) > self::rank($current);
    }

    /** Parse Shiprocket timestamps (they use IST and several formats). */
    public static function parseTime(mixed $value): ?Carbon
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }
        foreach (['d m Y H:i:s', 'Y-m-d H:i:s', 'd-m-Y H:i:s', 'Y-m-d H:i'] as $format) {
            try {
                $t = Carbon::createFromFormat($format, $value, 'Asia/Kolkata');
            } catch (\Throwable) {
                continue;
            }
            // Round trip guards against loose parsing ("2026-13-45 ...").
            if ($t && $t->format($format) === $value) {
                return $t->setTimezone(config('app.timezone'));
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $value)) {
            try {
                return Carbon::parse($value)->setTimezone(config('app.timezone'));
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
