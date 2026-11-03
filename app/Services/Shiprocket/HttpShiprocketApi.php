<?php

namespace App\Services\Shiprocket;

use App\Models\CourierBooking;
use App\Models\User;

/** The real Shiprocket: each call is delegated to the focused service that knows its endpoint. */
class HttpShiprocketApi implements ShiprocketApi
{
    public function __construct(
        private readonly ShiprocketServiceabilityService $serviceability,
        private readonly ShiprocketOrderService $orders,
        private readonly ShiprocketCancellationService $cancellation,
        private readonly ShiprocketTrackingService $tracking,
    ) {}

    public function quote(string $pickup, string $delivery, float $weightGrams, float $length, float $width, float $height, string $unit): array
    {
        return $this->serviceability->quote($pickup, $delivery, $weightGrams, $length, $width, $height, $unit);
    }

    public function createOrder(CourierBooking $booking, ?User $user): array
    {
        return $this->orders->createOrder($booking, $user);
    }

    public function findOrderByChannelId(string $channelOrderId): ?array
    {
        return $this->orders->findOrderByChannelId($channelOrderId);
    }

    public function assignAwb(string $shipmentId, string $courierCompanyId): array
    {
        return $this->orders->assignAwb($shipmentId, $courierCompanyId);
    }

    public function requestPickup(string $shipmentId): void
    {
        $this->orders->requestPickup($shipmentId);
    }

    public function cancelOrder(string $shiprocketOrderId): void
    {
        $this->cancellation->cancelOrder($shiprocketOrderId);
    }

    public function track(string $awb): array
    {
        return $this->tracking->fetch($awb);
    }
}
