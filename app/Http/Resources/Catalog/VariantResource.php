<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * One variant for the customer. The resource wraps {variant, mrp, payable, stock...}:
 * `price` is the MRP (struck through), `sale_price` is what the customer pays (selling price + Taksh charge).
 * The vendor's own money columns (commission, tax, settlement ...) are never part of it.
 */
class VariantResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $v = $this->variant;

        return [
            'id' => $v->id,
            'product_id' => $v->product_id,
            'sku' => $v->sku,
            'price' => $this->mrp,
            'sale_price' => $this->payable,
            'weight' => $v->weight,
            'length' => $v->length,
            'width' => $v->width,
            'height' => $v->height,
            'status' => $v->status,
            'stock_qty' => $this->stock_qty,
            'reserved_qty' => $this->reserved_qty,
            'available_stock' => $this->available_stock,
            'in_stock' => $this->in_stock,
            'variant_attributes' => $this->when($v->relationLoaded('variantAttributes'), fn () => $v->variantAttributes->map(fn ($va) => [
                'attribute' => $va->attribute ? ['id' => $va->attribute->id, 'name' => $va->attribute->name] : null,
                'attribute_value' => $va->attributeValue ? ['id' => $va->attributeValue->id, 'value' => $va->attributeValue->value] : null,
            ])->all()),
            'images' => $this->when($v->relationLoaded('images'), fn () => $v->images->map(fn ($img) => [
                'id' => $img->id, 'image_url' => $img->image_url, 'is_primary' => (bool) $img->is_primary,
            ])->all()),
        ];
    }
}
