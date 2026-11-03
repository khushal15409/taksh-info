<?php

namespace App\Services\Shiprocket;

use App\Helpers\CourierPricing;

/**
 * Courier options and rates for a parcel, from Shiprocket's serviceability endpoint:
 *
 *   GET courier/serviceability
 *     pickup_postcode, delivery_postcode  6-digit pincodes (no city/state needed)
 *     weight                              KILOGRAMS (the app sends grams: grams / 1000)
 *     length, breadth, height             CENTIMETRES (inch input x 2.54; stored values are untouched)
 *     cod=0                               prepaid only
 *
 * The response is interpreted here and only a small normalized shape leaves this class.
 * Rates are live and Shiprocket gives no validity window, so callers must not treat them as locked.
 */
class ShiprocketServiceabilityService
{
    public function __construct(private ShiprocketClient $client)
    {
    }

    /**
     * @return array{weight_kg: float, couriers: array<int, array<string, mixed>>}
     *
     * @throws ShiprocketException NO_SERVICE when nothing usable comes back
     */
    public function quote(
        string $pickupPincode,
        string $deliveryPincode,
        float $weightGrams,
        float $length,
        float $width,
        float $height,
        string $dimensionUnit
    ): array {
        $weightKg = round($weightGrams / 1000, 3);
        [$lengthCm, $widthCm, $heightCm] = CourierPricing::dimensionsToCm($length, $width, $height, $dimensionUnit);

        try {
            $body = $this->client->get('courier/serviceability', [
                'pickup_postcode' => $pickupPincode,
                'delivery_postcode' => $deliveryPincode,
                'weight' => $weightKg,
                'length' => round($lengthCm, 2),
                'breadth' => round($widthCm, 2),
                'height' => round($heightCm, 2),
                'cod' => 0,
            ]);
        } catch (ShiprocketException $e) {
            // Shiprocket answers 404 when no courier serves the lane.
            if ($e->category === ShiprocketException::CLIENT_ERROR && $e->httpStatus === 404) {
                throw new ShiprocketException('No courier service available.', ShiprocketException::NO_SERVICE, 404);
            }

            throw $e;
        }

        $companies = $body['data']['available_courier_companies'] ?? null;
        if (! is_array($companies)) {
            throw new ShiprocketException('Shiprocket serviceability response was invalid.', ShiprocketException::INVALID_RESPONSE);
        }

        $couriers = [];
        foreach ($companies as $company) {
            $option = is_array($company) ? $this->normalize($company) : null;
            if ($option !== null) {
                $couriers[] = $option;
            }
        }

        if ($couriers === []) {
            throw new ShiprocketException('No courier service available.', ShiprocketException::NO_SERVICE);
        }

        usort($couriers, fn ($a, $b) => $a['rate'] <=> $b['rate']);

        return ['weight_kg' => $weightKg, 'couriers' => $couriers];
    }

    /** Options without a courier id or a usable rate cannot be booked later, so they are dropped. */
    private function normalize(array $c): ?array
    {
        $id = $c['courier_company_id'] ?? null;
        $rate = $c['rate'] ?? null;
        if ($id === null || $id === '' || ! is_scalar($id) || ! is_numeric($rate) || (float) $rate < 0) {
            return null;
        }

        $days = $c['estimated_delivery_days'] ?? null;
        $chargeWeight = $c['charge_weight'] ?? null;

        return [
            'courier_company_id' => (string) $id,
            'courier_name' => isset($c['courier_name']) ? (string) $c['courier_name'] : null,
            'rate' => round((float) $rate, 2),
            'estimated_delivery_days' => is_numeric($days) ? (int) $days : null,
            'estimated_delivery_date' => isset($c['etd']) && is_string($c['etd']) ? $c['etd'] : null,
            'chargeable_weight' => is_numeric($chargeWeight) ? (float) $chargeWeight : null,
            'service_type' => array_key_exists('is_surface', $c) ? ($c['is_surface'] ? 'surface' : 'air') : null,
        ];
    }
}
