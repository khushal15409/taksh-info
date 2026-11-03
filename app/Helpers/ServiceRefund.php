<?php

namespace App\Helpers;

use InvalidArgumentException;

/** The refund a cancelled service booking gets: pure calculations from the service's snapshotted refund policy. */
class ServiceRefund
{
    /** Refund of an amount actually paid; never negative, never more than paid. */
    public static function amount(?string $type, float $value, float $paid): float
    {
        $paid = max(0.0, $paid);
        $value = max(0.0, $value);
        $refund = match ($type) {
            'percentage' => $paid * min($value, 100.0) / 100,
            'fixed' => min($value, $paid),
            default => 0.0,
        };

        return round(min($refund, $paid), 2);
    }

    /**
     * A policy that cannot be honoured (unknown type, negative value, percentage over 100, fixed above the charge) is an error,
     * not something to cap quietly.
     *
     * @return array{original_amount: float, refund_type: string, refund_value: float, refund_amount: float, non_refundable_amount: float}
     */
    public static function breakdown(?string $type, ?float $value, float $serviceCharge, float $paid): array
    {
        if (! in_array($type, ['percentage', 'fixed'], true) || $value === null || $value < 0) {
            throw new InvalidArgumentException('Refund policy is not configured for this service.');
        }
        if (($type === 'percentage' && $value > 100) || ($type === 'fixed' && $value > $serviceCharge)) {
            throw new InvalidArgumentException('Refund policy for this service is invalid.');
        }

        $paid = max(0.0, $paid);
        $refund = self::amount($type, $value, $paid);

        return [
            'original_amount' => round($paid, 2), 'refund_type' => $type, 'refund_value' => round($value, 2),
            'refund_amount' => $refund, 'non_refundable_amount' => round($paid - $refund, 2),
        ];
    }
}
