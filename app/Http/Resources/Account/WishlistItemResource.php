<?php

namespace App\Http\Resources\Account;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/** A saved product (wraps {row, product, final_price, mrp, in_stock}). */
class WishlistItemResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $p = $this->product;
        $thumb = ($p->images->where('is_primary', true)->first() ?? $p->images->first())?->image_url;

        return [
            'id' => $p->id,
            'product_id' => $p->id,
            'name' => $p->name,
            'price' => $this->final_price,
            'original_price' => $this->mrp > 0 ? $this->mrp : $this->final_price,
            'sale_price' => $this->final_price,
            'in_stock' => $this->in_stock,
            'thumbnail' => $thumb,
            'wishlist_id' => $this->row->id,
        ];
    }
}
