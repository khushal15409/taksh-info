<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\WebController;
use Illuminate\Contracts\View\View;

/** The OTP login page. The steps (send OTP, verify, resend timer) are small JS talking to /auth/send-otp and /auth/verify-otp. */
class LoginPageController extends WebController
{
    public function show(): View
    {
        return $this->page('auth.login');
    }
}
