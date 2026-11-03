<?php

namespace App\Http\Requests\Web\Account;

use App\Http\Requests\Concerns\ValidatesProfile;
use Illuminate\Foundation\Http\FormRequest;

/** The profile page form: same rules as the JSON endpoint, but a failure goes back to the form with the errors (and the typed values). */
class UpdateProfilePageRequest extends FormRequest
{
    use ValidatesProfile;

    protected $errorBag = 'profile';

    public function authorize(): bool
    {
        return true;
    }

    protected function getRedirectUrl(): string
    {
        return route('profile', ['tab' => 'profile']);
    }
}
