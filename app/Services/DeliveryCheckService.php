<?php

namespace App\Services;

use App\Exceptions\ProductNotFoundException;
use App\Helpers\QuickDelivery;
use App\Models\Vendor;
use App\Services\Quick\QuickAvailability;
use App\Repositories\Contracts\CartCatalogRepositoryInterface;
use App\Repositories\Contracts\DeliveryCheckRepositoryInterface;

/**
 * Can this product be delivered to a pincode? Standard products follow the vendor's service area and stock
 * (the same rule order placement enforces). Quick (30 min) products are served by the app's quick delivery,
 * which is judged by the radius around the shop (QuickAvailability), as the app does.
 */
class DeliveryCheckService
{
    public function __construct(
        private readonly DeliveryCheckRepositoryInterface $delivery,
        private readonly QuickAvailability $quick,
        private readonly CartCatalogRepositoryInterface $stock,
    ) {}

    /** @return array<string, mixed> */
    /**
     * @param  array{pincode: ?string, latitude: ?float, longitude: ?float}|null  $where  the customer's delivery place; when it is the pincode being
     *                                                                                  checked its coordinates make the 10 km test exact
     */
    public function check(int $productId, string $pincode, ?int $variantId = null, int $qty = 1, ?array $where = null): array
    {
        $product = $this->delivery->product($productId) ?? throw new ProductNotFoundException();
        $quick = (bool) $product->is_express_30;

        $respond = fn (bool $deliverable, string $messageKey, ?Vendor $vendor = null, array $extra = []) => $extra + [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'pincode' => $pincode,
            'is_deliverable' => $deliverable,
            'message' => __($messageKey),
            'vendor' => $vendor ? ['id' => $vendor->id, 'vendor_name' => $vendor->vendor_name, 'shop_name' => $vendor->shop_name] : null,
            'delivery_type' => $quick ? '30_min' : 'normal',
        ];

        if (! $product->vendor_id) {
            return $respond(false, 'api.delivery_check_no_vendor');
        }

        $vendor = $this->delivery->approvedVendor($product->vendor_id);
        if (! $vendor) {
            return $respond(false, 'api.delivery_check_vendor_unavailable');
        }

        if ($quick) {
            // Quick serves a radius around the shop, not pincodes: judge it where the customer is, else at the pincode's own position
            [$lat, $lng] = $where && ($where['pincode'] ?? null) === $pincode ? [$where['latitude'], $where['longitude']] : [null, null];
            if (! QuickDelivery::validCoordinates($lat, $lng) && ($record = $this->delivery->activePincode($pincode))) {
                [$lat, $lng] = [$record->latitude, $record->longitude];
            }
            if (! QuickDelivery::validCoordinates($lat, $lng) && QuickDelivery::normalizePincode($pincode) === null) {
                return $respond(false, 'api.delivery_check_quick_no_location', $vendor, ['availability_status' => QuickAvailability::LOCATION_REQUIRED]);
            }

            $product->loadMissing('variants');
            $verdict = $this->quick->check($product, $lat, $lng, $pincode, $variantId);
            $status = $verdict['status'];
            if ($verdict['can_order'] && $variantId !== null && $this->stock->availableStock((int) $product->vendor_id, $variantId) < max(1, $qty)) {
                $status = QuickAvailability::OUT_OF_STOCK;
            }

            return $respond($status === QuickAvailability::AVAILABLE, QuickAvailability::messageKey($status), $vendor, ['availability_status' => $status]);
        }

        if (! $vendor->servesPincode($pincode)) {
            return $respond(
                false,
                $this->delivery->activePincode($pincode) ? 'api.delivery_check_not_mapped' : 'api.delivery_check_pincode_unknown',
                $vendor
            );
        }

        if (! $this->delivery->vendorHasStock($product, $vendor, $variantId, max(1, $qty))) {
            return $respond(false, 'api.delivery_check_out_of_stock', $vendor);
        }

        return $respond(true, 'api.delivery_check_ok', $vendor);
    }
}
