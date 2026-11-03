<?php

namespace App\Services\Orders;

use App\Exceptions\CartProblemException;
use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;

/** Where the delivery person is (Quick orders): the last position they sent, and how far it is from the address. */
class OrderTrackingService
{
    public function __construct(private readonly OrderRepositoryInterface $orders) {}

    /** @return array{data: array<string, mixed>, message: string} */
    public function location(User $user, int $orderId): array
    {
        $order = $this->orders->forTracking($orderId) ?? throw new CartProblemException('api.order_not_found', 404);

        // only the customer who placed the order (or the assigned delivery person) may follow it
        $isOwner = (int) $order->user_id === $user->id;
        $isRider = $order->deliveryMan && (int) $order->deliveryMan->user_id === $user->id;

        if (! $isOwner && ! $isRider) {
            throw new CartProblemException('api.unauthorized', 403);
        }

        $loc = $order->deliveryLocation;

        if (! $loc) {
            return ['data' => ['order_id' => $order->id, 'order_status' => $order->order_status, 'location' => null, 'message' => 'Delivery location not yet available'], 'message' => 'api.location_not_available'];
        }

        $distanceKm = null;
        $nearby = false;
        if ($order->address && $order->address->latitude && $order->address->longitude) {
            $distanceKm = $this->distanceKm((float) $loc->latitude, (float) $loc->longitude, (float) $order->address->latitude, (float) $order->address->longitude);
            $nearby = $distanceKm * 1000 <= 100;
        }

        return ['data' => [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_status' => $order->order_status,
            'delivery_boy' => $order->deliveryMan ? ['id' => $order->deliveryMan->id, 'name' => $order->deliveryMan->name, 'mobile_number' => $order->deliveryMan->mobile_number] : null,
            'location' => ['latitude' => (float) $loc->latitude, 'longitude' => (float) $loc->longitude, 'updated_at' => $loc->updated_at?->toIso8601String()],
            'distance_to_destination' => $distanceKm ? round($distanceKm * 1000, 2) : null,
            'reached_nearby' => $nearby,
        ], 'message' => 'api.location_retrieved'];
    }

    /** Haversine distance in kilometres. */
    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
