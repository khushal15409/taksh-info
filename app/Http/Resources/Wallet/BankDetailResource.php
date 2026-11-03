<?php

namespace App\Http\Resources\Wallet;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/** A saved bank account. The account number is always masked: the website never sends it back in full. */
class BankDetailResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_holder_name' => $this->account_holder_name,
            'bank_name' => $this->bank_name,
            'account_number' => $this->maskedAccountNumber(),
            'ifsc_code' => $this->ifsc_code,
            'branch' => $this->branch,
            'upi' => $this->upi,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
