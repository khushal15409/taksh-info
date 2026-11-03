<?php

namespace App\Helpers;

use App\Repositories\Contracts\CheckoutRepositoryInterface;

/**
 * Income tax (section 194-O) is not held back from a vendor until the shop's sales for the financial year
 * cross the limit set in Settings > Vendor Charges. Only that vendor rule is needed to place an order.
 */
class TaxThreshold
{
    /** What this shop has sold since 1 April (worked out once per request, not once per order line). */
    private static array $vendorGross = [];

    public static function flush(): void
    {
        self::$vendorGross = [];
    }

    /** 1 April of the financial year today falls in. */
    public static function financialYearStart(?string $date = null): string
    {
        $time = $date ? strtotime($date) : time();
        $year = (int) date('Y', $time);

        return ((int) date('n', $time) >= 4 ? $year : $year - 1).'-04-01';
    }

    public static function vendorFyGross(int $vendorId): float
    {
        return self::$vendorGross[$vendorId] ??= app(CheckoutRepositoryInterface::class)->vendorSalesSince($vendorId, self::financialYearStart());
    }

    /** Has the shop crossed the limit, so TDS is held from now on? */
    public static function vendorTdsApplies(?int $vendorId): bool
    {
        return $vendorId && self::vendorFyGross($vendorId) >= VendorCharges::value('tds_194o_threshold');
    }
}
