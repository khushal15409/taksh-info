<?php

namespace App\Http\Requests\Web\Support;

use App\Support\SupportForms;
use Illuminate\Validation\Rule;

class VendorRegistrationRequest extends SupportFormRequest
{
    /** The bag the page reads this form's field errors from. */
    protected $errorBag = 'vendor';

    protected function formKey(): string
    {
        return 'vendor';
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->filled('gstin')) {
            $this->merge(['gstin' => strtoupper(trim($this->input('gstin')))]);
        }
    }

    public function rules(): array
    {
        return [
            'business' => ['required', 'string', 'max:255'], 'owner' => ['required', 'string', 'max:255'], 'phone' => $this->phoneRules(),
            'email' => ['required', 'email', 'max:255'], 'business_type' => ['required', Rule::in(array_keys(SupportForms::BUSINESS_TYPES))],
            'category' => ['required', 'string', 'max:100'], 'gstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/'],
            'city' => ['required', 'string', 'max:100'], 'pincode' => ['required', 'digits:6'], 'about' => ['nullable', 'string', 'max:1500'], 'agree' => ['accepted'],
        ];
    }
}
