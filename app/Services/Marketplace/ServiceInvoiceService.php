<?php

namespace App\Services\Marketplace;

use App\Exceptions\ServiceProblemException;
use App\Models\CourierBooking;
use App\Models\ServiceMarketplaceBooking as Booking;
use App\Models\User;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use App\Repositories\Contracts\ServiceBookingRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;

/**
 * The customer's invoice for a PAID service booking. Every figure is read from the booking itself, nothing is recomputed or
 * invented (no tax split is printed because none is stored on these bookings). It is shown as a printable page ("Save as PDF"
 * from the browser) rather than a PDF file, so the website needs no PDF library.
 */
class ServiceInvoiceService
{
    public function __construct(
        private readonly ServiceBookingRepositoryInterface $bookings,
        private readonly CourierBookingRepositoryInterface $couriers,
        private readonly SettingRepositoryInterface $settings,
    ) {}

    /** @return array<string, mixed> the data the invoice view prints */
    public function forBooking(User $user, string $ref): array
    {
        $booking = $this->bookings->findForUser($user->id, $ref) ?? throw new ServiceProblemException('Booking not found.', 404);
        if ($booking->payment_status !== 'paid') {
            throw new ServiceProblemException('Invoice is available once the payment is completed.', 422);
        }

        return $this->data($booking, $user);
    }

    /** @return array<string, mixed> the data the courier invoice view prints */
    public function forCourier(User $user, int $id): array
    {
        $booking = $this->couriers->detailForUser($user->id, $id) ?? throw new ServiceProblemException('Courier booking not found', 404);
        if (($booking->payment_status ?? null) !== 'paid') {
            throw new ServiceProblemException('Invoice is available once the payment is completed.', 422);
        }

        return $this->courierData($booking);
    }

    /** @return array<string, mixed> */
    private function courierData(CourierBooking $booking): array
    {
        $total = (float) $booking->total_amount;
        if ($booking->shiprocket_estimated_cost !== null && $booking->platform_charge !== null) {
            $lines = [
                ['description' => 'Courier shipping charge'.($booking->courier_name ? ' ('.$booking->courier_name.')' : ''), 'amount' => (float) $booking->shiprocket_estimated_cost],
                ['description' => 'Service charge', 'amount' => (float) $booking->platform_charge],
            ];
        } else {
            $partner = $booking->deliveryPartner?->name ?? $booking->courier_name;
            $lines = [['description' => 'Courier booking'.($partner ? ' ('.$partner.')' : ''), 'amount' => $total]];
        }
        $payment = $booking->payments->first();
        $grams = rtrim(rtrim(number_format((float) $booking->chargeable_weight_grams, 3, '.', ''), '0'), '.');

        return $this->base([
            'title' => 'Courier Booking Invoice',
            'invoice_no' => 'INV-'.strtoupper($booking->booking_number),
            'booking_number' => $booking->booking_number,
            'service_name' => 'Courier Booking',
            'invoice_date' => ($booking->paid_at ?? $booking->created_at)?->format('d-m-Y'),
            'booked_on' => $booking->created_at?->format('d-m-Y h:i A'),
            'bill_to_name' => $booking->customer_name,
            'bill_to_mobile' => $booking->customer_mobile,
            'bill_to_address' => $booking->pickup_address.($booking->pickup_pincode ? ' - '.$booking->pickup_pincode : ''),
            'details' => array_values(array_filter([
                ['label' => 'Pickup', 'value' => $booking->pickup_address.' - '.$booking->pickup_pincode],
                ['label' => 'Deliver to', 'value' => $booking->delivery_name.', '.$booking->delivery_mobile.', '.$booking->delivery_address.' - '.$booking->delivery_pincode],
                ['label' => 'Chargeable weight', 'value' => $grams.' g'],
                $booking->awb_code ? ['label' => 'AWB', 'value' => $booking->awb_code] : null,
            ])),
            'lines' => $lines,
            'total' => $total,
            'payment_status' => $booking->payment_status ?? 'pending',
            'payment_method' => $payment?->method ?: ($booking->payment_gateway ?: ''),
            'transaction_id' => $payment?->gateway_payment_id ?: ($booking->payment_transaction_id ?: ''),
            'paid_at' => $booking->paid_at?->format('d-m-Y h:i A'),
        ]);
    }

    /** @return array<string, mixed> */
    private function data(Booking $booking, User $user): array
    {
        $address = (array) $booking->address_snapshot;
        $full = implode(', ', array_filter([$address['address_line_1'] ?? '', $address['address_line_2'] ?? '', $address['landmark'] ?? '', $address['city'] ?? '', $address['state'] ?? '', $booking->pincode]));
        $payment = $booking->latestPayment;
        $total = (float) $booking->service_charge;
        $name = $booking->service->name ?? 'Service';

        return $this->base([
            'title' => 'Service Booking Invoice',
            'invoice_no' => 'INV-'.strtoupper($booking->booking_number),
            'booking_number' => $booking->booking_number,
            'service_name' => $name,
            'invoice_date' => ($booking->paid_at ?? $booking->created_at)?->format('d-m-Y'),
            'booked_on' => $booking->created_at?->format('d-m-Y h:i A'),
            'bill_to_name' => $address['name'] ?? $user->name ?? '',
            'bill_to_mobile' => $address['mobile'] ?? '',
            'bill_to_address' => $full,
            'details' => array_values(array_filter([
                ['label' => 'Service', 'value' => $name],
                ['label' => 'Service address', 'value' => $full],
                $booking->scheduled_for ? ['label' => 'Scheduled for', 'value' => $booking->scheduled_for->format('d-m-Y h:i A')] : null,
            ])),
            'lines' => [['description' => $name.' - service charge', 'amount' => $total]],
            'total' => $total,
            'payment_status' => $booking->payment_status ?? 'pending',
            'payment_method' => $payment?->method ?: ($payment?->gateway ?? ''),
            'transaction_id' => $payment?->gateway_payment_id ?? '',
            'paid_at' => $booking->paid_at?->format('d-m-Y h:i A'),
        ]);
    }

    /** The company block every invoice carries (Taksh's own details from the shared settings). */
    public function base(array $data): array
    {
        return $data + [
            'company_name' => $this->settings->value('company_legal_name') ?: 'Taksh All In One',
            'company_gstin' => (string) $this->settings->value('company_gstin'),
            'company_address' => (string) $this->settings->value('company_address'),
            'logo_src' => asset('assets/img/logo.png'),
        ];
    }
}
