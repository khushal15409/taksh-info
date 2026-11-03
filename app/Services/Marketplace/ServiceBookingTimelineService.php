<?php

namespace App\Services\Marketplace;

use App\Models\ServiceMarketplaceBooking as Booking;

/**
 * The customer's view of a booking's story: every status-history row worded for the customer (no internal ids, no provider
 * rejection reasons, admins shown as "support"). Nothing is invented. Expects `histories` to be loaded.
 */
class ServiceBookingTimelineService
{
    /** @return list<array{status: string, title: string, reason: ?string, at: ?string, actor: ?string}> */
    public function build(Booking $booking): array
    {
        $events = [];
        $assigned = 0;

        foreach ($booking->histories->sortBy('id') as $h) {
            $event = $this->describe($h, $assigned);
            if ($h->to_status === Booking::STATUS_PROVIDER_ASSIGNED) {
                $assigned++;
            }
            if ($event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    private function describe($h, int $assignedBefore): ?array
    {
        $from = $h->from_status;
        $to = $h->to_status;
        $reason = $h->reason;

        if (str_starts_with($to, 'refund_')) {
            $title = match ($to) {
                'refund_processing' => 'Refund processing',
                'refund_success' => 'Refund completed',
                'refund_failed' => 'Refund delayed',
                default => null,
            };

            return $title ? $this->event($to, $title, null, $h) : null;
        }

        // a row whose status did not change: payment notes and visit scheduling
        if ($from === $to) {
            return $this->event($to, match ($reason) {
                'Payment verified' => 'Payment successful',
                'Payment failed' => 'Payment failed',
                'New payment order' => 'Payment retried',
                'Schedule updated' => 'Visit scheduled',
                default => 'Payment received after cancellation',
            }, null, $h);
        }

        $showReason = false;
        $title = match ($to) {
            Booking::STATUS_PENDING => 'Booking created',
            Booking::STATUS_CONFIRMED => 'Booking confirmed',
            Booking::STATUS_PROVIDER_ASSIGNED => $reason === 'Reassigned' ? 'Provider changed' : 'Provider assigned',
            Booking::STATUS_PROVIDER_ACCEPTED => 'Provider accepted',
            Booking::STATUS_PROVIDER_REJECTED => 'Finding another provider',
            Booking::STATUS_IN_PROGRESS => 'Service started',
            Booking::STATUS_COMPLETED => 'Service completed',
            Booking::STATUS_CANCELLED => $h->changed_by_type === 'customer' ? 'You cancelled the booking' : 'Cancelled by support',
            default => null,
        };
        if ($to === Booking::STATUS_CANCELLED) {
            $showReason = true;
        }

        return $title ? $this->event($to, $title, $showReason ? $reason : null, $h) : null;
    }

    private function event(string $status, string $title, ?string $reason, $h): array
    {
        return [
            'status' => $status,
            'title' => $title,
            'reason' => $reason ?: null,
            'at' => $h->created_at?->toIso8601String(),
            'actor' => $h->changed_by_type === 'admin' ? 'support' : $h->changed_by_type,
        ];
    }
}
