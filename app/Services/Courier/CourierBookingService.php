<?php

namespace App\Services\Courier;

use App\Exceptions\ServiceProblemException;
use App\Helpers\CourierPricing;
use App\Models\CourierBooking;
use App\Models\User;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Creates a courier booking, as taksh-admin does. With Shiprocket (the normal case) the price is rebuilt from Shiprocket right
 * now: the cheapest courier is booked unless one was named, and nothing price-related comes from the client (the optional
 * `quoted_rate` only detects a stale quote). The payment order is created together with the booking; if that fails the booking
 * is kept and POST /service/courier/payment/create makes it later.
 */
class CourierBookingService
{
    public function __construct(
        private readonly CourierBookingRepositoryInterface $bookings,
        private readonly CourierQuoteService $quotes,
        private readonly CourierPaymentService $payments,
        private readonly CourierPhotoStore $photos,
    ) {}

    /** @return array<string, mixed> the booking summary the API answers with */
    public function create(User $user, array $v, ?UploadedFile $photo): array
    {
        $partner = null;
        $selection = null;

        if (empty($v['courier_delivery_partner_id'])) {
            $selection = $this->quotes->priceSelection(
                $v['pickup_pincode'], $v['delivery_pincode'], (float) $v['weight_grams'], (float) $v['length'], (float) $v['width'], (float) $v['height'],
                $v['dimension_unit'], $v['shiprocket_courier_company_id'] ?? null,
            );
            if (! empty($v['shiprocket_courier_company_id']) && isset($v['quoted_rate']) && CourierQuoteService::toPaise($v['quoted_rate']) !== $selection['rate_paise']) {
                throw ServiceProblemException::quoteChanged('The courier price has changed. Please refresh the quote.');
            }
        } else {
            $partner = $this->bookings->activePartner((int) $v['courier_delivery_partner_id']) ?? throw new ServiceProblemException('Delivery partner not found or inactive', 404);
        }

        $actual = (float) $v['weight_grams'];
        $volumetric = CourierPricing::volumetricWeightGrams((float) $v['length'], (float) $v['width'], (float) $v['height'], $v['dimension_unit']);
        $chargeable = CourierPricing::chargeableWeight($actual, $volumetric);
        $total = $selection ? $selection['total_paise'] / 100 : CourierPricing::priceForPartner($partner, $chargeable['chargeable_grams']);

        $photoPath = null;
        if ($photo && $photo->isValid()) {
            try {
                $photoPath = $this->photos->put($photo);
            } catch (\Throwable $e) {
                Log::error('Courier item photo upload failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                throw new ServiceProblemException('Failed to upload item photo', 500);
            }
        }

        try {
            $booking = $this->bookings->createBooking([
                'user_id' => $user->id,
                'customer_name' => $v['customer_name'], 'customer_mobile' => $v['customer_mobile'],
                'pickup_address' => $v['pickup_address'], 'pickup_pincode' => $v['pickup_pincode'],
                'delivery_name' => $v['delivery_name'], 'delivery_mobile' => $v['delivery_mobile'],
                'delivery_address' => $v['delivery_address'], 'delivery_pincode' => $v['delivery_pincode'],
                'weight_grams' => $actual, 'dimension_unit' => $v['dimension_unit'],
                'length' => $v['length'], 'width' => $v['width'], 'height' => $v['height'],
                'volumetric_weight_grams' => round($volumetric, 3), 'chargeable_weight_grams' => $chargeable['chargeable_grams'], 'chargeable_weight_source' => $chargeable['source'],
                'customer_notes' => $v['customer_notes'] ?? null, 'product_details' => $v['product_details'] ?? null, 'packaging_details' => $v['packaging_details'] ?? null,
                'item_photo_path' => $photoPath, 'courier_delivery_partner_id' => $partner?->id, 'total_amount' => $total, 'status' => 'pending',
            ] + ($selection ? [
                // pricing snapshot: the source of truth for payment create/verify
                'shiprocket_courier_company_id' => $selection['courier']['courier_company_id'],
                'courier_name' => $selection['courier']['courier_name'],
                'shiprocket_estimated_cost' => $selection['rate_paise'] / 100,
                'platform_charge' => $selection['platform_paise'] / 100,
                'shiprocket_estimated_delivery_days' => $selection['courier']['estimated_delivery_days'],
            ] : []));
        } catch (\Throwable $e) {
            Log::error('Courier booking create failed', ['error' => $e->getMessage()]);
            throw new ServiceProblemException('Failed to create courier booking', 500);
        }

        $payment = $selection ? $this->payments->startWithBooking($user, $booking) : null;
        $booking = $this->bookings->fresh($booking);

        return [
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'actual_weight_grams' => (float) $booking->weight_grams,
            'volumetric_weight_grams' => (float) $booking->volumetric_weight_grams,
            'chargeable_weight_grams' => (float) $booking->chargeable_weight_grams,
            'chargeable_weight_source' => $booking->chargeable_weight_source,
            'courier_delivery_partner_id' => $partner?->id,
            'partner_name' => $partner?->name ?? $booking->courier_name,
            'total_amount' => (float) $booking->total_amount,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status ?? 'pending',
            'item_photo_path' => $booking->item_photo_path,
        ] + ($selection ? [
            'shiprocket_courier_company_id' => $booking->shiprocket_courier_company_id,
            'courier_name' => $booking->courier_name,
            'shiprocket_rate' => (float) $booking->shiprocket_estimated_cost,
            'service_charge' => (float) $booking->platform_charge,
            'platform_charge' => (float) $booking->platform_charge,
            'estimated_delivery_days' => $booking->shiprocket_estimated_delivery_days,
            'pricing' => CourierQueryService::pricing($booking, null),
            'payment' => $payment,
        ] : []);
    }
}
