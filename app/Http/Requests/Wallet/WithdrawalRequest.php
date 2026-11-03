<?php

namespace App\Http\Requests\Wallet;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class WithdrawalRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'points' => ['required', 'numeric', 'min:'.(int) config('wallet.min_withdrawal_points', 100)],
            // only one of the customer's own accounts
            'bank_detail_id' => ['required', 'integer', Rule::exists('bank_details', 'id')->where('user_id', $this->user()?->id)],
        ];
    }
}
