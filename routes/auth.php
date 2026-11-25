<?php

use App\Http\Controllers\Ajax\Auth\AuthController;
use App\Http\Controllers\Ajax\Auth\ProfileController;
use Illuminate\Support\Facades\Route;

// Same endpoints and payloads as the Taksh API (/auth/*), served on the web guard (session + CSRF).
Route::prefix('auth')->name('auth.')->group(function () {
    // Public
    Route::post('send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:20,1')->name('send-otp');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:30,1')->name('verify-otp');
    Route::get('session', [AuthController::class, 'session'])->name('session');

    // Logged-in customers only
    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('profile', [ProfileController::class, 'show'])->name('profile');
        Route::post('update-profile', [ProfileController::class, 'update'])->name('update-profile');
    });
});
