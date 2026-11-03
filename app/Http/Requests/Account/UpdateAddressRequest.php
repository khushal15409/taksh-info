<?php

namespace App\Http\Requests\Account;

use App\Helpers\Phone;

class UpdateAddressRequest extends StoreAddressRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('mobile')) {
            $this->merge(['mobile' => Phone::normalize($this->input('mobile')) ?? $this->input('mobile')]);
        }
    }

    /** Only the fields that are sent are changed. */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'mobile' => ['sometimes', 'required', 'digits:10'],
            'address_line_1' => ['sometimes', 'required', 'string'],
            'address_line_2' => ['sometimes', 'nullable', 'string'],
            'landmark' => ['sometimes', 'nullable', 'string'],
            'pincode' => ['sometimes', 'required', 'string', 'max:10'],
            'state_id' => ['sometimes', 'required', 'exists:states,id'],
            'city_id' => ['sometimes', 'required', 'exists:cities,id'],
            'area_id' => ['sometimes', 'nullable', 'exists:areas,id'],
            'address_type' => ['sometimes', 'required', 'in:home,office,work,other'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
            'state_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'area_name' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }
}
