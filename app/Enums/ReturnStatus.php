<?php

namespace App\Enums;

/** Where a return request stands (same values as taksh-admin). */
enum ReturnStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PICKUP_SCHEDULED = 'pickup_scheduled';
    case PICKED_UP = 'picked_up';
    case QC_PASSED = 'qc_passed';
    case QC_FAILED = 'qc_failed';
    case REFUND_INITIATED = 'refund_initiated';
    case REPLACEMENT_INITIATED = 'replacement_initiated';
    case COMPLETED = 'completed';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::PICKUP_SCHEDULED => 'Pickup Scheduled',
            self::PICKED_UP => 'Picked Up',
            self::QC_PASSED => 'QC Passed',
            self::QC_FAILED => 'QC Failed',
            self::REFUND_INITIATED => 'Refund Initiated',
            self::REPLACEMENT_INITIATED => 'Replacement Initiated',
            self::COMPLETED => 'Completed',
            self::CLOSED => 'Closed',
        };
    }

    /** A request in one of these states is still being worked on, so the same line cannot be returned again. */
    public static function open(): array
    {
        return [self::PENDING->value, self::APPROVED->value, self::PICKUP_SCHEDULED->value];
    }
}
