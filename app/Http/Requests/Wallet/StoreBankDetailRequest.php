<?php

namespace App\Http\Requests\Wallet;

use App\Http\Requests\ApiFormRequest;

class StoreBankDetailRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['account_number' => preg_replace('/\s+/', '', (string) $this->input('account_number')), 'ifsc_code' => strtoupper(trim((string) $this->input('ifsc_code')))]);
    }

    public function rules(): array
    {
        return [
            'account_holder_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:34'],
            'ifsc_code' => ['required', 'string', 'size:11', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            'branch' => ['nullable', 'string', 'max:255'],
            'upi' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['ifsc_code.regex' => 'The IFSC code must be valid (e.g. SBIN0001234).'];
    }
}
