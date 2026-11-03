<?php

namespace App\Http\Resources\Order;

use App\Http\Resources\Account\AddressResource;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * An order as a CUSTOMER may read it: no QR / OTP handshake fields, no vendor money split (commission, tax, settlement),
 * no gateway response, and only the shop's identity from the vendor.
 */
class OrderResource extends ApiResource
{
    private const QUICK_CODES = ['30_min', 'express_30', 'quick'];

    private const PAYMENT_STATUS = ['paid' => 'paid', 'success' => 'paid', 'failed' => 'failed', 'refunded' => 'refunded', 'initiated' => 'pending', 'pending' => 'pending'];

    public function toArray(Request $request): array
    {
        $latest = $this->relationLoaded('latestPayment') ? $this->latestPayment : null;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'vendor_id' => $this->vendor_id,
            'address_id' => $this->address_id,
            'delivery_type' => $this->delivery_type,
            'is_express' => (bool) $this->is_express,
            // one backend-owned answer to "is this a Quick order?" for every stored code
            'is_quick' => (bool) $this->is_express || in_array($this->delivery_type, self::QUICK_CODES, true),
            'order_status' => $this->order_status,
            'items_total' => (float) $this->items_total,
            'taksh_charge_total' => (float) $this->taksh_charge_total,
            'platform_fee' => (float) $this->platform_fee,
            'quick_delivery_fee' => (float) $this->quick_delivery_fee,
            'discount_total' => (float) $this->discount_total,
            'grand_total' => (float) $this->grand_total,
            'payment_method' => $latest?->method,
            'payment_status' => $latest ? (self::PAYMENT_STATUS[$latest->status] ?? $latest->status) : null,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'estimated_delivery_time' => $this->estimated_delivery_time?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor ? [
                'id' => $this->vendor->id, 'shop_name' => $this->vendor->shop_name, 'shop_address' => $this->vendor->shop_address, 'shop_pincode' => $this->vendor->shop_pincode,
            ] : null),
            'address' => $this->whenLoaded('address', fn () => $this->address ? AddressResource::make($this->address)->resolve() : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(function ($item) {
                $product = $item->relationLoaded('productVariant') ? $item->productVariant?->product : null;

                return [
                    'id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'qty' => $item->qty,
                    'price' => (float) $item->price,
                    // the snapshot taken when the order was placed; older lines fall back to the live product
                    'product_name' => $item->product_name ?? $product?->name,
                    'image_url' => $item->image_url ?? ($product && $product->relationLoaded('images') ? $product->images->first()?->image_url : null),
                ];
            })->all()),
            'charges' => $this->whenLoaded('charges', fn () => $this->charges->map(fn ($c) => [
                'type' => $c->type, 'label' => $c->label, 'amount' => (float) $c->amount, 'is_discount' => (bool) $c->is_discount,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id, 'razorpay_order_id' => $p->razorpay_order_id, 'razorpay_payment_id' => $p->razorpay_payment_id,
                'status' => $p->status, 'amount' => $p->amount, 'currency' => $p->currency, 'method' => $p->method,   // never the gateway response
            ])->all()),
            'refunds' => $this->whenLoaded('refundTransactions', fn () => $this->refundTransactions->map(fn ($t) => [
                'id' => $t->id, 'amount' => (float) $t->amount, 'status' => $t->status, 'description' => $t->description, 'created_at' => $t->created_at?->toIso8601String(),
            ])->all()),
            'latest_payment' => $latest ? [
                'id' => $latest->id, 'razorpay_order_id' => $latest->razorpay_order_id, 'razorpay_payment_id' => $latest->razorpay_payment_id,
                'status' => $latest->status, 'amount' => $latest->amount, 'currency' => $latest->currency, 'method' => $latest->method,
            ] : null,
        ];
    }
}
