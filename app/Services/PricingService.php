<?php

namespace App\Services;

use App\Helpers\VendorCharges;
use App\Models\Product;

/**
 * Prices as taksh-admin shows them: the vendor's selling price never includes the Taksh charge,
 * so what the customer pays is selling price + Taksh charge (or the saved `customer_price`).
 */
class PricingService
{
    /**
     * Price of one piece for the cart and checkout: selling price (a sale price counts only above zero)
     * plus the Taksh charge, which is never part of the vendor's own price.
     *
     * @return array<string, float|bool|string>
     */
    public function calculateCheckoutPrice(Product $product, $variant): array
    {
        $mrp = (float) $variant->price;
        $sell = (float) (($variant->sale_price !== null && (float) $variant->sale_price > 0) ? $variant->sale_price : $variant->price);
        $discountPct = $mrp > 0.0 && $sell < $mrp ? round((($mrp - $sell) / $mrp) * 100, 2) : 0.0;
        $taksh = $this->takshCharge($product, $sell);

        return [
            'pricing_type' => strtoupper(trim((string) $product->pricing_type)) === 'MRP' ? 'MRP' : 'NONE_MRP',
            'mrp_price' => round($mrp, 2),
            'sell_price' => round($sell, 2),
            'discount_percentage' => $discountPct,
            'gst_percentage' => (float) ($product->gst_slab ?? 0),
            'taksh_charges_included' => false,
            'taksh_charge_amount' => round($taksh, 2),
            'final_sell_price' => round($sell + $taksh, 2),
        ];
    }

    /** The Taksh charge on a product: its own, else the band admin set for this selling price. */
    public function takshCharge(?Product $product, float $selling = 0): float
    {
        $charge = $product && $product->taksh_charge !== null
            ? (float) $product->taksh_charge
            : VendorCharges::takshChargeFor($selling);

        return round($charge, 2);
    }

    /** The MRP, to show struck through. Nothing is ever added to it. */
    public function mrp($variant): float
    {
        return round((float) ($variant->price ?? 0), 2);
    }

    /**
     * What the customer actually pays for one piece. The saved customer_price wins; an older row falls
     * back to selling price plus the charge, and a sale price passed in wins over both.
     */
    public function finalPrice(?Product $product, $variant, $salePrice = null): float
    {
        if ($salePrice !== null) {
            return round((float) $salePrice + $this->takshCharge($product, (float) $salePrice), 2);
        }

        $final = (float) ($variant->customer_price ?? 0);

        if ($final > 0) {
            return round($final, 2);
        }

        $selling = (float) ($variant?->sale_price ?: ($variant?->price ?? 0));

        return $selling > 0 ? round($selling + $this->takshCharge($product, $selling), 2) : 0.0;
    }

    /** Whole-number "% OFF": how far what the customer pays is below the MRP (0 hides the badge). */
    public function discountPercent(float $mrp, float $payable): int
    {
        if ($mrp <= 0.0 || $payable <= 0.0 || $payable >= $mrp) {
            return 0;
        }

        return (int) round((($mrp - $payable) / $mrp) * 100);
    }
}
