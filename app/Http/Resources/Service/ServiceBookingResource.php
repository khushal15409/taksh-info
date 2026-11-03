<?php

namespace App\Http\Resources\Service;

use App\Http\Resources\ApiResource;
use App\Models\ServiceMarketplaceBooking as Booking;
use App\Services\MediaUrlService;
use Illuminate\Http\Request;

/** A booking: the lightweight history row, the payment payload, or (with the detail flag) everything. */
class ServiceBookingResource extends ApiResource
{
    /** What the customer actually paid (0 until a payment is verified). */
    public static function paidAmount(Booking $b): float
    {
        return ! $b->paid_at || ! $b->latestPayment ? 0.0 : round($b->latestPayment->amount / 100, 2);
    }

    public function toArray(Request $request): array
    {
        $b = $this->resource;
        $addr = (array) $b->address_snapshot;
        $media = app(MediaUrlService::class);

        return [
            'id' => $b->id,
            'booking_number' => $b->booking_number,
            'status' => $b->status,
            'service' => ['id' => $b->service_service_id, 'name' => $b->service->name ?? null, 'image' => $media->url($b->service->image ?? null)],
            'service_charge' => (float) $b->service_charge,
            'paid_amount' => self::paidAmount($b),
            'payment_status' => $b->payment_status,
            'provider' => self::provider($b, false),
            'address' => ['line' => $addr['address_line_1'] ?? null, 'city' => $addr['city'] ?? null, 'state' => $addr['state'] ?? null, 'pincode' => $b->pincode],
            'refund' => $b->refund && $b->refund->status !== 'not_required' ? ['amount' => (float) $b->refund->requested_amount, 'status' => $b->refund_status] : null,
            'scheduled_for' => $b->scheduled_for?->toIso8601String(),
            'created_at' => $b->created_at?->toIso8601String(),
            'updated_at' => $b->updated_at?->toIso8601String(),
        ];
    }

    /** Name and photo from the moment of assignment; the mobile number only once the provider has accepted. Null while unassigned. */
    public static function provider(Booking $b, bool $detail): ?array
    {
        $accepted = [Booking::STATUS_PROVIDER_ACCEPTED, Booking::STATUS_IN_PROGRESS, Booking::STATUS_COMPLETED];
        if (! $b->provider || ! in_array($b->status, array_merge([Booking::STATUS_PROVIDER_ASSIGNED], $accepted), true)) {
            return null;
        }
        $isAccepted = in_array($b->status, $accepted, true);

        $out = ['name' => $b->provider->user->name ?? null, 'profile_image' => app(MediaUrlService::class)->url($b->provider->profile_image), 'status' => $isAccepted ? 'accepted' : 'assigned'];
        if ($detail) {
            $out += ['mobile' => $isAccepted ? ($b->provider->user->mobile ?? null) : null, 'assigned_at' => $b->assigned_at?->toIso8601String(), 'accepted_at' => $b->accepted_at?->toIso8601String()];
        }

        return $out;
    }
}
