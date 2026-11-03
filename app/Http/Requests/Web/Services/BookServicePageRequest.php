<?php

namespace App\Http\Requests\Web\Services;

use App\Helpers\Phone;
use Illuminate\Foundation\Http\FormRequest;

/** The home-service booking form: where the service is needed and when. Errors come back under the fields with what was typed. */
class BookServicePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mobile')) {
            $this->merge(['mobile' => Phone::normalize($this->input('mobile')) ?? $this->input('mobile')]);
        }
        if ($this->filled('scheduled_for')) {
            $this->merge(['scheduled_for' => str_replace('T', ' ', $this->input('scheduled_for'))]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return route('services.book', ['id' => $this->route('id')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'mobile' => ['required', 'digits:10'],
            'address_line_1' => ['required', 'string', 'max:500'], 'landmark' => ['nullable', 'string', 'max:255'],
            'pincode' => ['required', 'digits:6'], 'city' => ['nullable', 'string', 'max:255'],
            'scheduled_for' => ['nullable', 'date', 'after:now'], 'customer_notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'Enter a valid 10-digit mobile number', 'mobile.digits' => 'Enter a valid 10-digit mobile number',
            'pincode.required' => 'Enter a valid 6-digit pincode', 'pincode.digits' => 'Enter a valid 6-digit pincode',
            'scheduled_for.after' => 'Pick a time in the future', 'scheduled_for.date' => 'Pick a valid date and time',
            'idempotency_key.*' => 'This page expired. Please open the booking form again.',
        ];
    }
}
