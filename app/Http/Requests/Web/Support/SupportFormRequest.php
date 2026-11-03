<?php

namespace App\Http\Requests\Web\Support;

use App\Helpers\Phone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base of the enquiry forms: a failure goes back to the form with the errors under the fields and what was typed, in an error bag named
 * after the form. The mobile number may be typed with +91 or spaces; it is checked as the 10 digits it stands for.
 */
abstract class SupportFormRequest extends FormRequest
{
    abstract protected function formKey(): string;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => Phone::normalize($this->input('phone')) ?? $this->input('phone')]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return route($this->formKey() === 'help' ? 'help' : $this->formKey());
    }

    /** Rules every form shares for its mobile number. */
    protected function phoneRules(): array
    {
        return ['required', 'digits:10'];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Enter a valid mobile number', 'phone.digits' => 'Enter a valid mobile number',
            'pincode.required' => 'Enter a valid 6-digit pincode', 'pincode.digits' => 'Enter a valid 6-digit pincode',
            'email.required' => 'Enter a valid email', 'email.email' => 'Enter a valid email',
            'agree.accepted' => 'Please tick the box to continue', 'agree.required' => 'Please tick the box to continue',
            'gstin.regex' => 'Enter a valid 15-character GSTIN or leave blank',
            '*.required' => 'This field is required',
        ];
    }
}
