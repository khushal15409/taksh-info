<?php

use App\Http\Controllers\Web\Courier\CourierPageController;
use App\Http\Controllers\Web\Services\ServicePageController;
use Illuminate\Support\Facades\Route;

// Home services and courier. /services is open to everyone (the catalogue); booking, paying and the customer's own bookings need a login.
Route::get('/services', [ServicePageController::class, 'index'])->name('services');

Route::middleware('auth')->group(function () {
    Route::get('/services/{id}/book', [ServicePageController::class, 'create'])->whereNumber('id')->name('services.book');
    Route::post('/services/{id}/book', [ServicePageController::class, 'store'])->whereNumber('id')->middleware('throttle:20,1')->name('services.book.store');
    Route::get('/services/bookings/{id}', [ServicePageController::class, 'show'])->where('id', '[A-Za-z0-9]+')->name('services.bookings.show');
    Route::post('/services/bookings/{id}/pay', [ServicePageController::class, 'repay'])->where('id', '[A-Za-z0-9]+')->middleware('throttle:10,1')->name('services.bookings.repay');
    Route::post('/services/bookings/{id}/cancel', [ServicePageController::class, 'cancel'])->whereNumber('id')->middleware('throttle:10,1')->name('services.bookings.cancel');

    Route::post('/services/courier', [CourierPageController::class, 'store'])->middleware('throttle:10,1')->name('services.courier.store');
    Route::get('/services/courier/{id}', [CourierPageController::class, 'show'])->whereNumber('id')->name('services.courier.show');
    Route::post('/services/courier/{id}/cancel', [CourierPageController::class, 'cancel'])->whereNumber('id')->middleware('throttle:10,1')->name('services.courier.cancel');
    Route::post('/services/courier/{id}/track', [CourierPageController::class, 'track'])->whereNumber('id')->middleware('throttle:12,1')->name('services.courier.track');
});
