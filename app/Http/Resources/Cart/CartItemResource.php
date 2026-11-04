<?php

namespace App\Http\Resources\Cart;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/** One cart line (wraps {item, variant, product, pricing, line_total, available_stock, vendor_name, in_stock}). */
class CartItemResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->item->id,
            'product_variant_id' => $this->variant->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->variant->sku,
            'price' => $this->pricing['final_sell_price'],
            'qty' => $this->item->qty,
            'total' => $this->line_total,
            'image' => $this->image(),
            'vendor_id' => $this->product->vendor_id,
            'vendor_name' => $this->vendor_name,
            'available_stock' => $this->available_stock,
            'in_stock' => $this->in_stock,
            'is_express_30' => (bool) $this->product->is_express_30,
        ] + $this->pricing;
    }

    /** This option's own photo (primary first), else the product's - a variant without photos of its own still shows its product. */
    private function image(): ?string
    {
        $own = $this->variant->images;
        $all = $this->product->images ?? collect();

        return ($own->where('is_primary', true)->first() ?? $own->first() ?? $all->where('is_primary', true)->first() ?? $all->first())?->image_url;
    }
}
