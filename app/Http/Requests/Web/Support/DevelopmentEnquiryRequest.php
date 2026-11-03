<?php

namespace App\Http\Requests\Web\Support;

use App\Support\SupportForms;
use Illuminate\Validation\Rule;

class DevelopmentEnquiryRequest extends SupportFormRequest
{
    /** The bag the page reads this form's field errors from. */
    protected $errorBag = 'devrequest';

    protected function formKey(): string
    {
        return 'devrequest';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'phone' => $this->phoneRules(), 'email' => ['required', 'email', 'max:255'],
            'kind' => ['required', Rule::in(SupportForms::KINDS)], 'budget' => ['required', Rule::in(SupportForms::BUDGETS)], 'timeline' => ['required', Rule::in(SupportForms::TIMELINES)],
            'details' => ['required', 'string', 'min:10', 'max:1500'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + ['details.min' => 'Please describe your requirement (at least 10 characters)'];
    }
}
