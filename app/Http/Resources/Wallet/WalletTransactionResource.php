<?php

namespace App\Http\Resources\Wallet;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class WalletTransactionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $refund = $this->relationLoaded('refund') ? $this->refund : null;
        $withdrawal = $this->relationLoaded('walletWithdrawalRequest') ? $this->walletWithdrawalRequest : null;
        $order = $this->relationLoaded('order') ? $this->order : null;

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'refund_id' => $this->refund_id,
            'source' => $this->source,
            'type' => $this->type,                       // credit adds points, debit takes them away
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'order' => $order ? ['order_number' => $order->order_number, 'order_status' => $order->order_status] : null,
            'refund' => $refund ? [
                'id' => $refund->id, 'status' => $refund->status, 'amount' => (float) $refund->amount, 'payment_method' => $refund->payment_method,
                'return_id' => $refund->return_id, 'return_status' => $refund->relationLoaded('return') ? $refund->return?->status?->value : null,
            ] : null,
            'withdrawal_request' => $withdrawal ? ['id' => $withdrawal->id, 'status' => $withdrawal->status, 'points' => (float) $withdrawal->points] : null,
        ];
    }
}
