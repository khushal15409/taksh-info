<?php

namespace App\Http\Requests;

use App\Traits\ApiResponseTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Base for JSON endpoints: validation failures come back in the API's {success, message, data.errors} envelope. */
abstract class ApiFormRequest extends FormRequest
{
    use ApiResponseTrait;

    /** HTTP status of a validation failure (the API uses 422 for login, 400 for profile). */
    protected int $failureStatus = 422;

    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            $this->validationError($validator->errors()->toArray(), null, $this->failureStatus)
        );
    }
}
