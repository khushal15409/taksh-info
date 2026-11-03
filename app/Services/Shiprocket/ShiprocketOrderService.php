<?php

namespace App\Services\Shiprocket;

use App\Models\CourierBooking;
use App\Models\User;
use App\Helpers\CourierPricing;
use Illuminate\Support\Facades\Cache;

/**
 * Shiprocket order, AWB and pickup calls. HTTP and response interpretation only: it never touches
 * the database. State, locking and retries live in App\Services\Courier\CourierShipmentService.
 *
 * Endpoints (from Shiprocket's public API; response shapes are read defensively):
 *   POST orders/create/adhoc     -> order_id, shipment_id (+ awb_code when auto-assign is on)
 *   POST courier/assign/awb      -> awb_assign_status, response.data.awb_code
 *   POST courier/generate/pickup -> pickup_status
 *   GET  orders                  -> lookup by channel_order_id (recovers an order whose response was lost)
 *   GET  open/postcode/details   -> city/state for a pincode (bookings only store pincodes)
 */
class ShiprocketOrderService
{
    public function __construct(private ShiprocketClient $client)
    {
    }

    /**
     * @return array{order_id: string, shipment_id: string, awb_code: ?string, courier_name: ?string}
     *
     * @throws ShiprocketException
     */
    public function createOrder(CourierBooking $booking, ?User $user): array
    {
        $body = $this->client->post('orders/create/adhoc', $this->orderPayload($booking, $user));

        $orderId = $body['order_id'] ?? null;
        $shipmentId = $body['shipment_id'] ?? null;
        if (! $this->isId($orderId) || ! $this->isId($shipmentId)) {
            throw new ShiprocketException('Shiprocket order response was invalid.', ShiprocketException::INVALID_RESPONSE);
        }

        return [
            'order_id' => (string) $orderId,
            'shipment_id' => (string) $shipmentId,
            'awb_code' => $this->str($body['awb_code'] ?? null),
            'courier_name' => $this->str($body['courier_name'] ?? null),
        ];
    }

    /**
     * An order already created for this reference (used before re-creating after an unknown outcome).
     *
     * @return array{order_id: string, shipment_id: string, awb_code: ?string, courier_name: ?string}|null
     *
     * @throws ShiprocketException when the lookup itself fails
     */
    public function findOrderByChannelId(string $channelOrderId): ?array
    {
        try {
            $body = $this->client->get('orders', ['channel_order_id' => $channelOrderId]);
        } catch (ShiprocketException $e) {
            if ($e->category === ShiprocketException::CLIENT_ERROR && $e->httpStatus === 404) {
                return null; // nothing found for this reference
            }
            throw $e;
        }
        $rows = $body['data'] ?? null;
        if (! is_array($rows)) {
            throw new ShiprocketException('Shiprocket order lookup was invalid.', ShiprocketException::INVALID_RESPONSE);
        }

        foreach ($rows as $row) {
            if (! is_array($row) || (string) ($row['channel_order_id'] ?? '') !== $channelOrderId || ! $this->isId($row['id'] ?? null)) {
                continue;
            }
            $shipment = is_array($row['shipments'][0] ?? null) ? $row['shipments'][0] : [];
            if (! $this->isId($shipment['id'] ?? null)) {
                continue;
            }

            return [
                'order_id' => (string) $row['id'],
                'shipment_id' => (string) $shipment['id'],
                'awb_code' => $this->str($shipment['awb'] ?? null),
                'courier_name' => $this->str($shipment['courier'] ?? null),
            ];
        }

        return null;
    }

    /**
     * @return array{awb_code: string, courier_name: ?string}
     *
     * @throws ShiprocketException
     */
    public function assignAwb(string $shipmentId, string $courierCompanyId): array
    {
        $body = $this->client->post('courier/assign/awb', ['shipment_id' => $shipmentId, 'courier_id' => $courierCompanyId]);

        $data = $body['response']['data'] ?? [];
        $awb = is_array($data) ? $this->str($data['awb_code'] ?? null) : null;
        if (($body['awb_assign_status'] ?? 0) != 1 || $awb === null) {
            // 200 with no AWB: Shiprocket refused (wallet balance, courier unavailable, ...).
            throw new ShiprocketException('Shiprocket did not assign an AWB.', ShiprocketException::CLIENT_ERROR, 200);
        }

        return ['awb_code' => $awb, 'courier_name' => $this->str($data['courier_name'] ?? null)];
    }

    /**
     * @throws ShiprocketException
     */
    public function requestPickup(string $shipmentId): void
    {
        $body = $this->client->post('courier/generate/pickup', ['shipment_id' => [$shipmentId]]);

        $already = str_contains(strtolower((string) json_encode($body['response'] ?? $body['message'] ?? '')), 'already');
        if (($body['pickup_status'] ?? 0) != 1 && ! $already) {
            throw new ShiprocketException('Shiprocket did not schedule the pickup.', ShiprocketException::CLIENT_ERROR, 200);
        }
    }

    /** Order payload from trusted booking data only. */
    private function orderPayload(CourierBooking $booking, ?User $user): array
    {
        [$lengthCm, $widthCm, $heightCm] = CourierPricing::dimensionsToCm(
            (float) $booking->length, (float) $booking->width, (float) $booking->height, $booking->dimension_unit
        );
        $declared = round((float) config('shiprocket.default_declared_value'), 2);
        [$city, $state] = $this->cityState($booking->delivery_pincode);

        $payload = [
            'order_id' => $booking->booking_number,
            'order_date' => ($booking->paid_at ?? $booking->created_at ?? now())->format('Y-m-d H:i'),
            'pickup_location' => (string) config('shiprocket.pickup_location'),
            // The buyer is the delivery contact; shipping is billing.
            'billing_customer_name' => $booking->delivery_name,
            'billing_address' => $booking->delivery_address,
            'billing_city' => $city,
            'billing_state' => $state,
            'billing_country' => 'India',
            'billing_pincode' => $booking->delivery_pincode,
            'billing_phone' => $booking->delivery_mobile,
            'shipping_is_billing' => true,
            'order_items' => [[
                'name' => trim((string) $booking->product_details) !== '' ? mb_substr($booking->product_details, 0, 200) : (string) config('shiprocket.default_item_name'),
                'sku' => $booking->booking_number,
                'units' => 1,
                'selling_price' => $declared,
            ]],
            // Razorpay already collected the money.
            'payment_method' => 'Prepaid',
            'sub_total' => $declared,
            'length' => round($lengthCm, 2),
            'breadth' => round($widthCm, 2),
            'height' => round($heightCm, 2),
            'weight' => round((float) $booking->weight_grams / 1000, 3),
            // The pickup location is a fixed one registered in Shiprocket; keep the customer's own details visible to ops.
            'comment' => mb_substr('Customer pickup: '.$booking->customer_name.', '.$booking->customer_mobile.', '.$booking->pickup_address.' - '.$booking->pickup_pincode, 0, 500),
        ];
        if ($user && filled($user->email)) {
            $payload['billing_email'] = $user->email;
        }

        return $payload;
    }

    /**
     * @return array{0: string, 1: string}
     *
     * @throws ShiprocketException a 4xx-class failure when Shiprocket cannot resolve the pincode
     */
    private function cityState(string $pincode): array
    {
        $found = Cache::get("shiprocket:postcode:$pincode");
        if (! is_array($found)) {
            $body = $this->client->get('open/postcode/details', ['postcode' => $pincode]);
            $d = $body['postcode_details'] ?? null;
            $city = is_array($d) ? (is_array($d['city'] ?? null) ? ($d['city'][0] ?? null) : ($d['city'] ?? null)) : null;
            $state = is_array($d) ? ($d['state'] ?? null) : null;
            if (! is_string($city) || $city === '' || ! is_string($state) || $state === '') {
                throw new ShiprocketException('Shiprocket could not resolve the delivery pincode.', ShiprocketException::CLIENT_ERROR, 422);
            }
            $found = [$city, $state];
            Cache::put("shiprocket:postcode:$pincode", $found, now()->addDay());
        }

        return $found;
    }

    private function isId(mixed $v): bool
    {
        return (is_int($v) && $v > 0) || (is_string($v) && $v !== '' && ctype_digit($v));
    }

    private function str(mixed $v): ?string
    {
        return is_scalar($v) && (string) $v !== '' ? (string) $v : null;
    }
}
