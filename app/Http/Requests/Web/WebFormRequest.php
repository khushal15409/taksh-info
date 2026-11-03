<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base for small page actions (change a quantity, apply a coupon): a failure goes back to where the customer was with the first
 * error as a toast, instead of a page full of field errors.
 */
abstract class WebFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        session()->flash(\App\Http\Controllers\Web\WebController::FLASH_KEY, ['type' => 'error', 'message' => $validator->errors()->first()]);

        throw new HttpResponseException(redirect()->back()->withInput());
    }
}
