<?php

namespace App\Services\Quick;

use App\Helpers\QuickDelivery;
use App\Models\Product;
use App\Repositories\Contracts\CartCatalogRepositoryInterface;
use App\Repositories\Contracts\QuickVendorRepositoryInterface;

/**
 * Can a Quick product be ordered at a place right now - the verdict taksh-admin's QuickAvailability gives the app. Anything that cannot
 * be established is NOT available (a missing place, seller or variant never reads as "orderable").
 */
class QuickAvailability
{
    public const AVAILABLE = 'available';

    public const OUT_OF_STOCK = 'out_of_stock';

    public const AREA_UNSERVICEABLE = 'area_unserviceable';

    public const LOCATION_REQUIRED = 'location_required';

    public const VENDOR_UNAVAILABLE = 'vendor_unavailable';

    public const VARIANT_UNAVAILABLE = 'variant_unavailable';

    public const PRODUCT_UNAVAILABLE = 'product_unavailable';

    public function __construct(
        private readonly QuickVendorRepositoryInterface $vendors,
        private readonly QuickServiceability $serviceability,
        private readonly CartCatalogRepositoryInterface $catalog,
    ) {}

    /** The single decision table (pure). */
    public static function classify(bool $locationValid, bool $vendorApproved, bool $hasActiveVariant, bool $vendorServes, int $freeStock): string
    {
        return match (true) {
            ! $vendorApproved => self::VENDOR_UNAVAILABLE,
            ! $hasActiveVariant => self::VARIANT_UNAVAILABLE,
            ! $locationValid => self::LOCATION_REQUIRED,
            ! $vendorServes => self::AREA_UNSERVICEABLE,
            default => $freeStock > 0 ? self::AVAILABLE : self::OUT_OF_STOCK,
        };
    }

    public static function messageKey(string $status): string
    {
        return 'api.express.availability.'.$status;
    }

    /**
     * The status of $product (one variant when $variantId is given) for a place given as coordinates and/or a pincode.
     *
     * @return array{status: string, can_order: bool, serviceable: bool}
     */
    public function check(Product $product, mixed $lat, mixed $lng, mixed $pincode = null, ?int $variantId = null): array
    {
        $locationValid = QuickDelivery::validCoordinates($lat, $lng) || QuickDelivery::normalizePincode($pincode) !== null;
        $vendor = $product->vendor_id ? $this->vendors->vendor((int) $product->vendor_id) : null;
        $approved = $vendor && $vendor->status === 'approved';

        $inCatalogue = $product->status === 'active' && $product->approval_status === 'approved' && (bool) $product->is_express_30;
        $variants = $product->variants->where('status', 'active');
        if ($variantId !== null) {
            $variants = $variants->where('id', $variantId);
        }

        $serves = $locationValid && $approved && $this->serviceability->vendorServes($vendor, $lat, $lng, $pincode);

        $free = 0;
        foreach ($variants as $variant) {
            $free = max($free, $this->catalog->availableStock((int) $product->vendor_id, (int) $variant->id));
        }

        $status = $inCatalogue
            ? self::classify($locationValid, (bool) $approved, $variants->isNotEmpty(), $serves, $free)
            : self::PRODUCT_UNAVAILABLE;

        return ['status' => $status, 'can_order' => $status === self::AVAILABLE, 'serviceable' => $locationValid && $serves];
    }
}
