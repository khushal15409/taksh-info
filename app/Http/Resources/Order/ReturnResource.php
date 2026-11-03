<?php

namespace App\Http\Resources\Order;

use App\Enums\ReturnStatus;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/** A return request as the customer sees it: the line, how many pieces, the pictures sent, where it stands and the refund. */
class ReturnResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $line = $this->relationLoaded('orderItem') ? $this->orderItem : null;
        $product = $line && $line->relationLoaded('productVariant') ? $line->productVariant?->product : null;

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_number' => $this->relationLoaded('order') ? $this->order?->order_number : null,
            'order_item_id' => $this->order_item_id,
            'product_name' => $line?->product_name ?? $product?->name,
            'image_url' => $line?->image_url ?? ($product && $product->relationLoaded('images') ? $product->images->first()?->image_url : null),
            'quantity' => $this->relationLoaded('items') ? $this->returnedQty() : null,
            'reason' => $this->reason,
            'resolution' => $this->resolution?->value,
            'resolution_label' => $this->resolution?->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'rejection_reason' => $this->rejection_reason,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'pickup_scheduled_at' => $this->pickup_scheduled_at?->toIso8601String(),
            'picked_up_at' => $this->picked_up_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => ['id' => $m->id, 'file_url' => $m->file_url])->all()),
            'refund' => $this->whenLoaded('refund', fn () => $this->refund ? [
                'amount' => (float) $this->refund->amount, 'payment_method' => $this->refund->payment_method, 'status' => $this->refund->status, 'processed_at' => $this->refund->processed_at?->toIso8601String(),
            ] : null),
            'status_history' => $this->whenLoaded('statusLogs', fn () => $this->statusLogs->sortBy('created_at')->map(fn ($l) => [
                'status' => $l->status, 'status_label' => ReturnStatus::tryFrom($l->status)?->label() ?? $l->status, 'notes' => $l->notes, 'created_at' => $l->created_at?->toIso8601String(),
            ])->values()->all()),
        ];
    }
}
