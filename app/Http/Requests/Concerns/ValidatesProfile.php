<?php

namespace App\Http\Requests\Concerns;

use App\Helpers\Phone;
use Illuminate\Validation\Rule;

/** The profile rules, shared by the JSON endpoint (update-profile) and the profile page form, so both accept exactly the same input. */
trait ValidatesProfile
{
    protected function prepareForValidation(): void
    {
        if ($this->has('mobile')) {
            $this->merge(['mobile' => Phone::normalize($this->input('mobile')) ?? $this->input('mobile')]);
        }
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'mobile' => ['required', 'digits:10', Rule::unique('users', 'mobile')->ignore($userId)],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'The first name field is required.',
            'first_name.max' => 'The first name may not be greater than 100 characters.',
            'last_name.required' => 'The last name field is required.',
            'last_name.max' => 'The last name may not be greater than 100 characters.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'The email has already been taken.',
            'mobile.required' => 'The mobile field is required.',
            'mobile.digits' => 'The mobile must be exactly 10 digits.',
            'mobile.unique' => 'The mobile has already been taken.',
        ];
    }
}
