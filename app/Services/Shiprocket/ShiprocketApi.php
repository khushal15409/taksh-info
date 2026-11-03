<?php

namespace App\Services\Shiprocket;

use App\Models\CourierBooking;
use App\Models\User;

/**
 * Everything the website asks Shiprocket (the same account taksh-admin uses). HTTP and response interpretation only: never the
 * database. Every method throws ShiprocketException on failure, with a category that says whether the outcome is known.
 */
interface ShiprocketApi
{
    /** @return array{weight_kg: float, couriers: list<array<string, mixed>>} live options, cheapest first. @throws ShiprocketException NO_SERVICE when none */
    public function quote(string $pickup, string $delivery, float $weightGrams, float $length, float $width, float $height, string $unit): array;

    /** @return array{order_id: string, shipment_id: string, awb_code: ?string, courier_name: ?string} */
    public function createOrder(CourierBooking $booking, ?User $user): array;

    /** An order already created for this reference, or null. @return array{order_id: string, shipment_id: string, awb_code: ?string, courier_name: ?string}|null */
    public function findOrderByChannelId(string $channelOrderId): ?array;

    /** @return array{awb_code: string, courier_name: ?string} */
    public function assignAwb(string $shipmentId, string $courierCompanyId): array;

    public function requestPickup(string $shipmentId): void;

    /** Succeeds when the order is cancelled, including when it already was. CLIENT_ERROR = Shiprocket refused. */
    public function cancelOrder(string $shiprocketOrderId): void;

    /** @return array{events: list<array<string, mixed>>, tracking_url: ?string} */
    public function track(string $awb): array;
}
