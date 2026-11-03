<?php

namespace App\Http\Requests\Auth;

use App\Helpers\Phone;
use App\Http\Requests\ApiFormRequest;

class VerifyOtpRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['mobile' => Phone::normalize($this->input('mobile')) ?? $this->input('mobile')]);
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'otp' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:10'],
            'guest_token' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
            'otp.regex' => 'The OTP must contain digits only.',
        ];
    }
}
