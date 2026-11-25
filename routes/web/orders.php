<?php

use App\Http\Controllers\Web\Orders\OrderPageController;
use App\Http\Controllers\Web\Orders\ReturnPageController;
use Illuminate\Support\Facades\Route;

// One order, and the return flow. (The order list is the "My Orders" tab of /profile.) The pages are open to guests - they show a login prompt.
Route::get('/tracking', [OrderPageController::class, 'show'])->middleware('urlkey:order,id')->name('tracking');
Route::get('/return', [ReturnPageController::class, 'create'])->middleware('urlkey:order,id')->name('returns');

Route::middleware('auth')->group(function () {
    Route::post('/tracking/{id}/cancel', [OrderPageController::class, 'cancel'])->where('id', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->middleware(['urlkey:order', 'throttle:20,1'])->name('tracking.cancel');
    Route::post('/return', [ReturnPageController::class, 'store'])->middleware('throttle:10,1')->name('returns.store');
    Route::get('/returns/{id}', [ReturnPageController::class, 'show'])->where('id', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->middleware('urlkey:return')->name('returns.show');
});
