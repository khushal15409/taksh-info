<?php

namespace App\Http\Requests\Web\Support;

use App\Support\SupportForms;
use Illuminate\Validation\Rule;

class DeliveryPartnerRequest extends SupportFormRequest
{
    /** The bag the page reads this form's field errors from. */
    protected $errorBag = 'delivery';

    protected function formKey(): string
    {
        return 'delivery';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'phone' => $this->phoneRules(), 'city' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'digits:6'], 'vehicle' => ['required', Rule::in(SupportForms::VEHICLES)], 'licence' => ['required', 'string', 'max:50'],
            'shift' => ['required', Rule::in(SupportForms::SHIFTS)], 'agree' => ['accepted'],
        ];
    }
}
