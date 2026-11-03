<?php

namespace App\Services;

use App\Exceptions\ProductNotFoundException;
use App\Models\Vendor;
use App\Repositories\Contracts\DeliveryCheckRepositoryInterface;

/**
 * Can this product be delivered to a pincode? Standard products follow the vendor's service area and stock
 * (the same rule order placement enforces). Quick (30 min) products are served by the app's quick delivery,
 * which the website does not offer yet, so they are reported as not deliverable here.
 */
class DeliveryCheckService
{
    public function __construct(private readonly DeliveryCheckRepositoryInterface $delivery) {}

    /** @return array<string, mixed> */
    public function check(int $productId, string $pincode, ?int $variantId = null, int $qty = 1): array
    {
        $product = $this->delivery->product($productId) ?? throw new ProductNotFoundException();
        $quick = (bool) $product->is_express_30;

        $respond = fn (bool $deliverable, string $messageKey, ?Vendor $vendor = null) => [
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
            return $respond(false, 'api.delivery_check_quick_on_app', $vendor);
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
