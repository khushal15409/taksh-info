<?php

use App\Http\Controllers\Ajax\Support\SupportRequestController;
use Illuminate\Support\Facades\Route;

// Enquiry forms (callback, delivery partner, vendor, development) - same paths and payloads as the Taksh API.
Route::middleware('auth')->post('request-callback', [SupportRequestController::class, 'callback'])->middleware('throttle:5,1')->name('support.callback');
Route::post('delivery-boy/join-request', [SupportRequestController::class, 'join'])->middleware('throttle:5,1')->name('support.join');
Route::post('vendor-request', [SupportRequestController::class, 'vendor'])->middleware('throttle:5,1')->name('support.vendor');
Route::post('development-request', [SupportRequestController::class, 'development'])->middleware('throttle:5,1')->name('support.development');
