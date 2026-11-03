<?php

namespace App\Services\Marketplace;

use App\Exceptions\ServiceProblemException;
use App\Http\Resources\Service\ServiceBookingResource;
use App\Http\Resources\Service\ServiceResource;
use App\Models\ServiceMarketplaceBooking as Booking;
use App\Models\User;
use App\Repositories\Contracts\ServiceBookingRepositoryInterface;
use App\Services\MediaUrlService;

/** Read side of the home services: catalogue, the customer's booking history and one booking's detail. */
class ServiceBookingQueryService
{
    public function __construct(
        private readonly ServiceBookingRepositoryInterface $bookings,
        private readonly ServiceBookingTimelineService $timeline,
        private readonly MediaUrlService $media,
    ) {}

    public function catalog(): array
    {
        return ServiceResource::collection($this->bookings->activeServices())->resolve();
    }

    public function history(User $user, ?string $category, int $perPage): array
    {
        $page = $this->bookings->paginateForUser($user->id, $category, $perPage);

        return [
            'bookings' => ServiceBookingResource::collection(collect($page->items()))->resolve(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ];
    }

    public function detail(User $user, string $ref): array
    {
        $booking = $this->bookings->detailForUser($user->id, $this->bookings->resolveId($user->id, $ref)) ?? throw new ServiceProblemException('Booking not found.', 404);

        $data = ServiceBookingResource::make($booking)->resolve();
        $cancelled = $booking->status === Booking::STATUS_CANCELLED;

        return array_replace($data, [
            'currency' => $booking->currency,
            'pincode' => $booking->pincode,
            'customer_notes' => $booking->customer_notes,
            'paid_at' => $booking->paid_at?->toIso8601String(),
            'address' => collect((array) $booking->address_snapshot)->only(['name', 'mobile', 'address_line_1', 'address_line_2', 'landmark', 'city', 'state', 'pincode'])->all(),
            'timestamps' => [
                'created_at' => $booking->created_at?->toIso8601String(), 'assigned_at' => $booking->assigned_at?->toIso8601String(),
                'accepted_at' => $booking->accepted_at?->toIso8601String(), 'started_at' => $booking->started_at?->toIso8601String(),
                'completed_at' => $booking->completed_at?->toIso8601String(),
            ],
            'cancellation' => $cancelled ? [
                'cancelled_at' => $booking->cancelled_at?->toIso8601String(), 'reason' => $booking->cancellation_reason,
                'refund_amount' => (float) ($booking->refund->requested_amount ?? 0), 'refund_status' => $booking->refund_status,
                'refunded_amount' => (float) ($booking->refund->refunded_amount ?? 0),
            ] : null,
            'can_cancel' => in_array($booking->status, ServiceBookingCancellationService::CUSTOMER_CAN_CANCEL, true),
            'provider' => ServiceBookingResource::provider($booking, true),
            'service' => ['id' => $booking->service_service_id, 'name' => $booking->service->name ?? null, 'short_description' => $booking->service->short_description ?? null, 'image' => $this->media->url($booking->service->image ?? null)],
            'payment' => $this->payment($booking),
            'pending_payment' => $this->pendingPayment($booking),
            'timeline' => $this->timeline->build($booking),
            'updated_at' => $booking->updated_at?->toIso8601String(),
        ]);
    }

    /** The Razorpay order of an unpaid, live booking (what the pay button opens), or null. */
    private function pendingPayment(Booking $b): ?array
    {
        $p = $b->latestPayment;
        if ($b->payment_status === 'paid' || $b->status !== Booking::STATUS_PENDING || ! $p || $p->status !== 'initiated') {
            return null;
        }

        return ['razorpay_order_id' => $p->gateway_order_id, 'amount_in_paise' => (int) $p->amount, 'currency' => $p->currency];
    }

    private function payment(Booking $b): array
    {
        $refund = $b->refund && $b->refund->status !== 'not_required' ? $b->refund : null;

        return [
            'paid_amount' => ServiceBookingResource::paidAmount($b),
            'status' => $b->payment_status,
            'paid_at' => $b->paid_at?->toIso8601String(),
            'refund' => $refund ? [
                'amount' => (float) $refund->requested_amount, 'refunded_amount' => (float) $refund->refunded_amount,
                'status' => $b->refund_status, 'refunded_at' => $refund->processed_at?->toIso8601String(),
            ] : null,
        ];
    }
}
