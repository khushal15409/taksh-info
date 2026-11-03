<?php

namespace App\Http\Requests\Web\Wallet;

use App\Http\Requests\Web\WebFormRequest;
use Illuminate\Validation\Rule;

/** Asking for a payout of points to one of the customer's own bank accounts: the same rules as POST /wallet/withdrawal-requests. */
class WithdrawalPageRequest extends WebFormRequest
{
    public function rules(): array
    {
        return [
            'points' => ['required', 'numeric', 'min:'.(int) config('wallet.min_withdrawal_points', 100)],
            'bank_detail_id' => ['required', 'integer', Rule::exists('bank_details', 'id')->where('user_id', $this->user()?->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'points.required' => 'Enter the points to withdraw',
            'points.min' => 'Minimum '.(int) config('wallet.min_withdrawal_points', 100).' points required for withdrawal',
            'bank_detail_id.*' => 'Add a bank account first',
        ];
    }
}
