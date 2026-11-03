<?php

namespace App\Helpers;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * The money split of a product price (selling price and Taksh charge already include GST, so it is taken out).
 * Same arithmetic as the PriceBreakup file in taksh-admin and taksh-vendor; the website only needs the
 * order-time copy (forOrderItem) that is stored on each order line.
 */
class PriceBreakup
{
    /** Every amount for one selling price. A percent that is null is switched off. */
    public static function make(float $sellingPrice, array $settings): array
    {
        $slab = (float) ($settings['gst_slab'] ?? 0) / 100;

        // the Taksh charge follows the price band admin set, unless this product carries its own
        $charge = isset($settings['taksh_charge']) && $settings['taksh_charge'] !== null
            ? (float) $settings['taksh_charge']
            : VendorCharges::takshChargeFor($sellingPrice);

        $actualPrice = $sellingPrice / (1 + $slab);
        // the charge is a service fee, so it carries its own GST rate
        $chargeBase = $charge / (1 + VendorCharges::value('taksh_charge_gst_percent') / 100);
        // TCS, TDS and GST are cut on the price and the charge without their GST
        $taxBase = $actualPrice + $chargeBase;

        $commission = $actualPrice * (float) ($settings['commission_percent'] ?? 0) / 100;
        $tcs = $taxBase * (float) ($settings['tcs_percent'] ?? 0) / 100;
        $tds = $taxBase * (float) ($settings['tds_percent'] ?? 0) / 100;
        $gstCut = $taxBase * (float) ($settings['vendor_gst_percent'] ?? 0) / 100;

        // the payment gateway's cut on what the customer paid, carried by the vendor
        $gateway = VendorCharges::gatewayFeeOn($sellingPrice);

        return [
            'actual_price' => round($actualPrice, 2),
            'gst_amount' => round($sellingPrice - $actualPrice, 2),
            'taksh_charge' => round($charge, 2),
            'taksh_charge_base' => round($chargeBase, 2),
            'taksh_charge_gst' => round($charge - $chargeBase, 2),
            'commission_amount' => round($commission, 2),
            'tcs_amount' => round($tcs, 2),
            'tds_amount' => round($tds, 2),
            'vendor_gst_amount' => round($gstCut, 2),
            'gateway_fee' => $gateway['gateway_fee'],
            'gateway_fee_gst' => $gateway['gateway_fee_gst'],
            'settlement_amount' => round($sellingPrice - $commission - $tcs - $tds - $gstCut - $gateway['gateway_fee'] - $gateway['gateway_fee_gst'], 2),
            'customer_price' => round($sellingPrice + $charge, 2),
        ];
    }

    /** The money to copy onto an order item, for one piece, worked out fresh at order time. */
    public static function forOrderItem(ProductVariant $variant, Product $product): array
    {
        $selling = (float) ($variant->sale_price ?: $variant->price);
        $settings = self::settingsOf($product);

        // income tax is only held back once the shop's sales for the year cross the limit
        if (! TaxThreshold::vendorTdsApplies($product->vendor_id)) {
            $settings['tds_percent'] = 0;
        }

        $amounts = self::make($selling, $settings);

        return [
            // amounts, per piece
            'taksh_charge' => $amounts['taksh_charge'],
            'taksh_charge_base' => $amounts['taksh_charge_base'],
            'taksh_charge_gst' => $amounts['taksh_charge_gst'],
            'gateway_fee' => $amounts['gateway_fee'],
            'gateway_fee_gst' => $amounts['gateway_fee_gst'],
            'return_charge' => round((float) ($product->return_charge ?? VendorCharges::value('default_return_charge')), 2),
            'return_days' => (int) ($product->return_days ?: VendorCharges::value('default_return_days')),
            'actual_price' => $amounts['actual_price'],
            'gst_amount' => $amounts['gst_amount'],
            'commission_amount' => $amounts['commission_amount'],
            'tcs_amount' => $amounts['tcs_amount'],
            'tds_amount' => $amounts['tds_amount'],
            'vendor_gst_amount' => $amounts['vendor_gst_amount'],
            'settlement_amount' => $amounts['settlement_amount'],
            'customer_price' => $amounts['customer_price'],
            // the rates it was all worked out at, so the line can explain itself later
            'gst_slab' => $settings['gst_slab'],
            'commission_percent' => $settings['commission_percent'],
            'tcs_percent' => $settings['tcs_percent'],
            'tds_percent' => $settings['tds_percent'],
            'vendor_gst_percent' => $settings['vendor_gst_percent'],
            'gateway_percent' => VendorCharges::value('gateway_percent'),
            'gateway_gst_percent' => VendorCharges::value('gateway_gst_percent'),
        ];
    }

    private static function settingsOf(Product $product): array
    {
        return [
            'gst_slab' => $product->gst_slab,
            'taksh_charge' => $product->taksh_charge,
            'commission_percent' => $product->commission_percent,
            'tcs_percent' => $product->tcs_percent,
            'tds_percent' => $product->tds_percent,
            'vendor_gst_percent' => $product->vendor_gst_percent,
        ];
    }
}
