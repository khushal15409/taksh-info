<?php

namespace App\Http\Requests\Account;

use App\Helpers\Phone;
use App\Http\Requests\ApiFormRequest;

class StoreAddressRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('mobile')) {
            $this->merge(['mobile' => Phone::normalize($this->input('mobile')) ?? $this->input('mobile')]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'digits:10'],
            'address_line_1' => ['required', 'string'],
            // state, city and area are normally worked out from the pincode; the ids are only for clients that send all three
            'state_id' => ['nullable', 'exists:states,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'pincode' => ['required', 'string', 'max:10'],
            'address_type' => ['required', 'in:home,office,work,other'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
            'address_line_2' => ['nullable', 'string'],
            'landmark' => ['nullable', 'string'],
            // used only to register a pincode that is not known yet
            'state_name' => ['nullable', 'string', 'max:100'],
            'city_name' => ['nullable', 'string', 'max:100'],
            'area_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    /** `address_type` is stored as `type`, and "office" as "work". */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        if (isset($data['address_type'])) {
            $data['type'] = $data['address_type'] === 'office' ? 'work' : $data['address_type'];
            unset($data['address_type']);
        }

        return $key ? ($data[$key] ?? $default) : $data;
    }
}
