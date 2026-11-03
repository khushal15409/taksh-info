<?php

namespace App\Services\Courier;

use App\Exceptions\ServiceProblemException;
use App\Helpers\CourierPricing;
use App\Models\CourierDeliveryPartner;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use App\Services\Shiprocket\ShiprocketApi;
use App\Services\Shiprocket\ShiprocketException;
use Illuminate\Support\Facades\Log;

/**
 * Shiprocket quote + service charge: the single place where the customer's price is built, always on the server:
 * total = Shiprocket rate + service charge, in whole paise. The service charge is the one the admin set on the "Courier Booking"
 * service (stored on the booking as platform_charge); SHIPROCKET_PLATFORM_CHARGE is only the fallback.
 */
class CourierQuoteService
{
    public function __construct(
        private readonly ShiprocketApi $shiprocket,
        private readonly CourierBookingRepositoryInterface $bookings,
    ) {}

    public static function toPaise(float|string|null $rupees): int
    {
        return CourierPricing::toPaise($rupees);
    }

    /** Neither the admin's service_charge nor the fallback set (or invalid) is an error, never a default. */
    public function platformChargePaise(): int
    {
        $value = $this->bookings->courierServiceCharge() ?? config('shiprocket.platform_charge');
        if (! is_numeric($value) || (float) $value < 0) {
            Log::error('Courier service charge is not configured (Courier Booking service_charge / SHIPROCKET_PLATFORM_CHARGE)');
            throw new ServiceProblemException('Courier booking is temporarily unavailable. Please try again later.', 503);
        }

        return self::toPaise($value);
    }

    /**
     * What POST /service/courier/quote answers. With both pincodes the options are live from Shiprocket; without them the
     * legacy local partners are priced by weight.
     *
     * @return array{data: array<string, mixed>, message: string}
     */
    public function quote(array $v): array
    {
        if (! empty($v['pickup_pincode']) && ! empty($v['delivery_pincode'])) {
            try {
                $result = $this->options($v['pickup_pincode'], $v['delivery_pincode'], (float) $v['weight_grams'], (float) $v['length'], (float) $v['width'], (float) $v['height'], $v['dimension_unit']);
            } catch (ServiceProblemException $e) {
                throw new ServiceProblemException($e->getMessage(), $e->status() === 409 ? 422 : $e->status());
            }

            return ['data' => ['actual_weight_grams' => round((float) $v['weight_grams'], 3), 'weight_kg' => $result['weight_kg'], 'couriers' => $result['couriers']], 'message' => 'Delivery partners fetched successfully'];
        }

        $actual = (float) $v['weight_grams'];
        $volumetric = CourierPricing::volumetricWeightGrams((float) $v['length'], (float) $v['width'], (float) $v['height'], $v['dimension_unit']);
        $chargeable = CourierPricing::chargeableWeight($actual, $volumetric);

        return ['data' => [
            'actual_weight_grams' => round($actual, 3),
            'volumetric_weight_grams' => round($volumetric, 3),
            'chargeable_weight_grams' => $chargeable['chargeable_grams'],
            'chargeable_weight_source' => $chargeable['source'],
            'quotes' => $this->bookings->activePartners()->map(fn (CourierDeliveryPartner $p) => [
                'courier_delivery_partner_id' => $p->id, 'name' => $p->name, 'slug' => $p->slug,
                'total_amount' => CourierPricing::priceForPartner($p, $chargeable['chargeable_grams']),
            ])->values()->all(),
        ], 'message' => 'Quote computed successfully'];
    }

    /**
     * Fresh options for a parcel, each with the service charge and customer total added.
     *
     * @return array{weight_kg: float, couriers: list<array<string, mixed>>}
     */
    public function options(string $pickup, string $delivery, float $weightGrams, float $l, float $w, float $h, string $unit): array
    {
        $charge = $this->platformChargePaise();
        $result = $this->fetch($pickup, $delivery, $weightGrams, $l, $w, $h, $unit);
        $result['couriers'] = array_map(fn ($c) => $c + [
            'service_charge' => $charge / 100, 'platform_charge' => $charge / 100, 'total_amount' => (self::toPaise($c['rate']) + $charge) / 100,
        ], $result['couriers']);

        return $result;
    }

    /**
     * Revalidate one selected courier against Shiprocket right now. Without a courier id the cheapest available courier is chosen.
     *
     * @return array{courier: array<string, mixed>, rate_paise: int, platform_paise: int, total_paise: int}
     */
    public function priceSelection(string $pickup, string $delivery, float $weightGrams, float $l, float $w, float $h, string $unit, ?string $courierCompanyId = null): array
    {
        $platform = $this->platformChargePaise();
        $result = $this->fetch($pickup, $delivery, $weightGrams, $l, $w, $h, $unit);

        foreach ($result['couriers'] as $courier) {
            if ($courierCompanyId === null || $courier['courier_company_id'] === $courierCompanyId) {
                $rate = self::toPaise($courier['rate']);

                return ['courier' => $courier, 'rate_paise' => $rate, 'platform_paise' => $platform, 'total_paise' => $rate + $platform];
            }
        }

        throw ServiceProblemException::quoteChanged('This courier is no longer available. Please refresh the quote.');
    }

    private function fetch(string $pickup, string $delivery, float $weightGrams, float $l, float $w, float $h, string $unit): array
    {
        try {
            return $this->shiprocket->quote($pickup, $delivery, $weightGrams, $l, $w, $h, $unit);
        } catch (ShiprocketException $e) {
            Log::warning('Shiprocket quote failed', ['pickup_pincode' => $pickup, 'delivery_pincode' => $delivery, 'category' => $e->category, 'status' => $e->httpStatus]);

            if ($e->category === ShiprocketException::NO_SERVICE) {
                throw ServiceProblemException::quoteChanged('Courier delivery is not available between these pincodes. Please refresh the quote.');
            }
            if ($e->category === ShiprocketException::CLIENT_ERROR && in_array($e->httpStatus, [400, 422], true)) {
                throw new ServiceProblemException('Please check the pickup and delivery pincodes', 422);
            }
            // setup problems are spelled out on a developer machine; customers only ever see the generic message
            if (app()->environment('local', 'testing')) {
                if ($e->category === ShiprocketException::NOT_CONFIGURED) {
                    throw new ServiceProblemException('Shiprocket is not configured: set SHIPROCKET_EMAIL and SHIPROCKET_PASSWORD in .env, then run php artisan config:clear.', 503);
                }
                if ($e->category === ShiprocketException::AUTHENTICATION) {
                    throw new ServiceProblemException('Shiprocket login was rejected: check SHIPROCKET_EMAIL / SHIPROCKET_PASSWORD (use an API user, not the main account login).', 503);
                }
            }

            throw new ServiceProblemException('Courier rates are temporarily unavailable. Please try again later.', 503);
        }
    }
}
