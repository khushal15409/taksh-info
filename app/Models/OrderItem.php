<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_variant_id', 'qty', 'price',
        // what the customer ordered, as it was when the order was placed
        'product_name', 'image_url',
        // the money for one piece, copied from the product when the order was placed
        'taksh_charge', 'actual_price', 'gst_amount', 'commission_amount', 'tcs_amount', 'tds_amount', 'settlement_amount',
        'vendor_gst_amount', 'taksh_charge_base', 'taksh_charge_gst', 'return_charge', 'return_days', 'customer_price',
        // the rates it was worked out at
        'gst_slab', 'commission_percent', 'tcs_percent', 'tds_percent', 'vendor_gst_percent',
        'gateway_fee', 'gateway_fee_gst', 'gateway_percent', 'gateway_gst_percent',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
