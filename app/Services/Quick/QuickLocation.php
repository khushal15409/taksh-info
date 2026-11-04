<?php

namespace App\Services\Quick;

use App\Helpers\QuickDelivery;
use App\Repositories\Contracts\AddressRepositoryInterface;
use App\Repositories\Contracts\LocationRepositoryInterface;
use Illuminate\Http\Request;

/**
 * Where the customer wants Quick products delivered: a signed-in customer's default saved address, else the location this browser
 * picked (cookie `taksh_loc` = "pincode,latitude,longitude", written by the location dialog; any part may be empty).
 */
class QuickLocation
{
    public const COOKIE = 'taksh_loc';

    public function __construct(
        private readonly AddressRepositoryInterface $addresses,
        private readonly LocationRepositoryInterface $locations,
    ) {}

    /**
     * The delivery place of this request for the header chip: what Quick uses, with a short label ("Barwani 451666").
     * One answer per request, so the header and the Quick page can never disagree.
     *
     * @return array{pincode: ?string, latitude: ?float, longitude: ?float, address_id: ?int, label: string}
     */
    public function current(Request $request): array
    {
        if ($request->attributes->has('taksh.location')) {
            return $request->attributes->get('taksh.location');
        }

        $where = $this->for($request);
        $city = null;
        if ($where['pincode'] !== null) {
            try {
                $city = $this->locations->areaForPincode($where['pincode'])?->city?->name;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $where['address_id'] = $request->attributes->get('taksh.address_id');
        $where['label'] = trim(($city ? $city.' ' : '').($where['pincode'] ?? ''));
        $request->attributes->set('taksh.location', $where);

        return $where;
    }

    /**
     * Priority: a pincode typed on the Quick page (?pincode=), the place this browser picked (cookie), then a signed-in customer's
     * default saved address.
     *
     * @return array{pincode: ?string, latitude: ?float, longitude: ?float}
     */
    public function for(Request $request): array
    {
        $typed = QuickDelivery::normalizePincode($request->query('pincode'));
        if ($typed !== null) {
            return $this->clean($typed, null, null);
        }

        if (! $this->forgotten($request)) {
            [$pincode, $lat, $lng] = array_pad(explode(',', (string) $request->cookie(self::COOKIE, ''), 3), 3, null);
            $picked = $this->clean($pincode, $lat, $lng);
            if ($picked['pincode'] !== null || $picked['latitude'] !== null) {
                return $picked;
            }
        }

        $user = $request->user();
        $address = $user ? $this->addresses->forUser($user->id)->sortByDesc(fn ($a) => [(int) $a->is_default, $a->id])->first() : null;

        if ($address) {
            $request->attributes->set('taksh.address_id', (int) $address->id);   // the saved address the place came from (the picker marks it)
        }

        return $address ? $this->clean($address->pincode, $address->latitude, $address->longitude) : $this->clean(null, null, null);
    }

    /** ?location=saved: ignore the picked place and use the saved address again. */
    public function forgotten(Request $request): bool
    {
        return $request->query('location') === 'saved';
    }

    /** The cookie value that remembers a typed pincode. */
    public static function cookieValue(string $pincode): string
    {
        return $pincode.',,';
    }

    /** @return array{pincode: ?string, latitude: ?float, longitude: ?float} */
    private function clean(mixed $pincode, mixed $lat, mixed $lng): array
    {
        $valid = QuickDelivery::validCoordinates($lat, $lng);

        return [
            'pincode' => QuickDelivery::normalizePincode($pincode),
            'latitude' => $valid ? (float) $lat : null,
            'longitude' => $valid ? (float) $lng : null,
        ];
    }
}
