<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** A delivered product waiting for a rating (wraps {row, product}). */
class PendingReviewResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => (int) $this->row->product_id,
            'product_name' => $this->product->name ?: $this->row->product_name,
            'image_url' => $this->product->images->first()?->image_url ?: $this->row->image_url,
            'order_id' => (int) $this->row->order_id,
            'order_number' => $this->row->order_number,
            'delivered_at' => $this->row->delivered_at ? Carbon::parse($this->row->delivered_at)->toIso8601String() : null,
        ];
    }
}
