<?php

namespace App\Services\Courier;

use App\Exceptions\ServiceProblemException;
use App\Models\CourierBooking;
use App\Models\User;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use App\Services\MediaUrlService;
use App\Services\Shiprocket\ShiprocketStatus;
use Illuminate\Support\Str;

/** Read side of the courier bookings: the customer's history, and one booking with its tracking timeline. */
class CourierQueryService
{
    public function __construct(
        private readonly CourierBookingRepositoryInterface $bookings,
        private readonly MediaUrlService $media,
    ) {}

    /** What the customer pays: the Shiprocket charge plus the service charge. */
    public static function pricing(CourierBooking $b, ?float $paidAmount): array
    {
        return [
            'shiprocket_cost' => $b->shiprocket_estimated_cost !== null ? (float) $b->shiprocket_estimated_cost : null,
            'service_charge' => $b->platform_charge !== null ? (float) $b->platform_charge : null,
            'platform_charge' => $b->platform_charge !== null ? (float) $b->platform_charge : null,
            'total_amount' => (float) $b->total_amount,
            'paid_amount' => $paidAmount,
            'currency' => 'INR',
        ];
    }

    /** Customer wording for the shipment state; a paid booking with no shipment yet reads "Pending". */
    public static function shipmentLabel(CourierBooking $b): ?string
    {
        if ($b->shipment_status !== null) {
            return ShiprocketStatus::label($b->shipment_status);
        }

        return ($b->payment_status ?? null) === 'paid' ? ShiprocketStatus::label('pending') : null;
    }

    public function history(User $user, int $perPage): array
    {
        $page = $this->bookings->paginateForUser($user->id, max(1, min(100, $perPage)));

        return [
            'bookings' => collect($page->items())->map(fn (CourierBooking $b) => [
                'id' => $b->id, 'booking_number' => $b->booking_number, 'status' => $b->status, 'booking_status' => $b->status,
                'payment_status' => $b->payment_status ?? null, 'customer_name' => $b->customer_name,
                'pickup_pincode' => $b->pickup_pincode, 'delivery_pincode' => $b->delivery_pincode,
                'chargeable_weight_grams' => (float) $b->chargeable_weight_grams, 'total_amount' => (float) $b->total_amount,
                'shiprocket_cost' => $b->shiprocket_estimated_cost !== null ? (float) $b->shiprocket_estimated_cost : null,
                'service_charge' => $b->platform_charge !== null ? (float) $b->platform_charge : null,
                'partner' => $b->deliveryPartner ? ['id' => $b->deliveryPartner->id, 'name' => $b->deliveryPartner->name] : null,
                'courier_name' => $b->courier_name, 'awb_code' => $b->awb_code, 'shipment_status' => $b->shipment_status,
                'shipment_status_label' => self::shipmentLabel($b),
                'created_at' => $b->created_at->toDateTimeString(), 'updated_at' => $b->updated_at?->toDateTimeString(),
            ])->values()->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ];
    }

    public function detail(User $user, int $id): array
    {
        $b = $this->bookings->detailForUser($user->id, $id) ?? throw new ServiceProblemException('Courier booking not found', 404);

        $timeline = $this->timeline($b);
        $paid = $b->payments->first();
        $paidAmount = $paid ? $paid->amount / 100 : (($b->payment_status ?? null) === 'paid' ? (float) $b->total_amount : null);
        $latest = $timeline === [] ? null : end($timeline);
        $refund = $b->refunds->first();

        return [
            'id' => $b->id, 'booking_number' => $b->booking_number, 'status' => $b->status, 'payment_status' => $b->payment_status ?? null,
            'customer_name' => $b->customer_name, 'customer_mobile' => $b->customer_mobile,
            'pickup_address' => $b->pickup_address, 'pickup_pincode' => $b->pickup_pincode,
            'delivery_name' => $b->delivery_name, 'delivery_mobile' => $b->delivery_mobile, 'delivery_address' => $b->delivery_address, 'delivery_pincode' => $b->delivery_pincode,
            'weight_grams' => (float) $b->weight_grams, 'dimension_unit' => $b->dimension_unit,
            'length' => (float) $b->length, 'width' => (float) $b->width, 'height' => (float) $b->height,
            'volumetric_weight_grams' => (float) $b->volumetric_weight_grams, 'chargeable_weight_grams' => (float) $b->chargeable_weight_grams, 'chargeable_weight_source' => $b->chargeable_weight_source,
            'customer_notes' => $b->customer_notes, 'product_details' => $b->product_details, 'packaging_details' => $b->packaging_details,
            'item_photo_path' => $this->media->url($b->item_photo_path),
            'delivery_partner' => $b->deliveryPartner ? ['id' => $b->deliveryPartner->id, 'name' => $b->deliveryPartner->name, 'slug' => $b->deliveryPartner->slug] : null,
            'total_amount' => (float) $b->total_amount, 'courier_name' => $b->courier_name,
            'shiprocket_courier_company_id' => $b->shiprocket_courier_company_id, 'estimated_delivery_days' => $b->shiprocket_estimated_delivery_days,
            'shipment_status' => $b->shipment_status, 'awb_code' => $b->awb_code, 'pickup_status' => $b->pickup_status, 'tracking_url' => $b->tracking_url,
            'latest_tracking_event' => $latest ? ['status' => $latest['status'] ?? $latest['title'], 'description' => $latest['description'], 'location' => $latest['location'], 'event_time' => $latest['event_time']] : null,
            'created_at' => $b->created_at->toDateTimeString(),
            'booking_status' => $b->status, 'updated_at' => $b->updated_at?->toDateTimeString(),
            'pricing' => self::pricing($b, $paidAmount),
            'payment' => ['status' => $b->payment_status ?? null, 'paid_amount' => $paidAmount, 'paid_at' => $b->paid_at?->toDateTimeString()],
            'courier' => ['name' => $b->courier_name, 'courier_company_id' => $b->shiprocket_courier_company_id, 'awb_code' => $b->awb_code, 'tracking_url' => $b->tracking_url, 'estimated_delivery_days' => $b->shiprocket_estimated_delivery_days],
            'shipment' => [
                'shipment_id' => $b->shiprocket_shipment_id, 'status' => $b->shipment_status, 'status_label' => self::shipmentLabel($b),
                'pickup_status' => $b->pickup_status, 'awb_assigned' => filled($b->awb_code),
            ],
            'tracking_timeline' => $timeline,
            'cancellation' => ['status' => $b->status === 'cancelled' ? 'cancelled' : null, 'cancelled_at' => $b->cancelled_at?->toDateTimeString(), 'reason' => $b->cancellation_reason],
            'refund' => $refund ? ['status' => $refund->status, 'amount' => (float) $refund->amount] : null,
            // the website's own hints for the buttons
            'can_cancel' => $this->canCancel($b),
        ];
    }

    /** Partners the admin offers (legacy local pricing): the K5 list. */
    public function partners(): array
    {
        return $this->bookings->activePartners()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug])->values()->all();
    }

    /** Stored tracking events, oldest first (already loaded and ordered). Read-only. */
    private function timeline(CourierBooking $b): array
    {
        return ($b->shipment?->trackingEvents ?? collect())->map(fn ($e) => [
            'status' => $e->normalized_status,
            'title' => ShiprocketStatus::label($e->normalized_status) ?? Str::title(Str::lower($e->status)),
            'description' => $e->description, 'location' => $e->location, 'event_time' => $e->event_time?->toDateTimeString(),
        ])->values()->all();
    }

    private function canCancel(CourierBooking $b): bool
    {
        if (! $b->shiprocket_courier_company_id || $b->status === 'cancelled') {
            return false;
        }
        $stage = $b->shipment?->shipment_status ?? $b->shipment_status;

        return $b->status === 'paid' && $b->payment_status === 'paid' && in_array($stage, [null, 'pending', 'order_created', 'awb_assigned', 'pickup_requested'], true);
    }
}
