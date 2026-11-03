<?php

namespace App\Http\Controllers\Ajax\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly AuthService $auth, private readonly \App\Services\GuestCartSession $guestCart) {}

    /** POST /auth/send-otp */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $sent = $this->auth->sendOtp($request->validated('mobile'));

        return $this->success([
            'message' => $sent['message'],
            'expires_at' => $sent['expires_at']->toDateTimeString(),
        ], 'api.otp_sent');
    }

    /** POST /auth/verify-otp - signs the customer in (web session instead of the app's token). */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = $this->auth->verifyOtp(
            $request->validated('mobile'),
            $request->validated('otp'),
            $this->guestCart->token(),   // the browser's own guest cart (session), never a token sent in the request
        );
        $this->guestCart->forget();

        Auth::login($user);
        $request->session()->regenerate();

        return $this->success([
            'user' => UserResource::make($user)->resolve(),
            'csrf_token' => csrf_token(),
        ], 'api.otp_verified');
    }

    /** POST /auth/logout */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->success(['csrf_token' => csrf_token()], 'api.logged_out');
    }

    /** GET /auth/session - lets the page re-check the login (e.g. after the back button). Always 200. */
    public function session(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'authenticated' => (bool) $user,
            'user' => $user ? UserResource::make($user)->resolve() : null,
        ], 'api.session_fetched');
    }
}
