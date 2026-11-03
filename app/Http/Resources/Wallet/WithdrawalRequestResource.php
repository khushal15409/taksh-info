<?php

namespace App\Http\Resources\Wallet;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class WithdrawalRequestResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'bank_detail_id' => $this->bank_detail_id,
            'points' => (float) $this->points,
            'amount_rupees' => (float) $this->amount_rupees,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'bank_detail' => $this->relationLoaded('bankDetail') && $this->bankDetail ? BankDetailResource::make($this->bankDetail)->resolve() : null,
        ];
    }
}
