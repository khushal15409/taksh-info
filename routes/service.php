<?php

use App\Http\Controllers\Ajax\Courier\CourierController;
use App\Http\Controllers\Ajax\Service\ServiceBookingController;
use App\Http\Controllers\Ajax\Service\ServiceCatalogController;
use App\Http\Controllers\Ajax\Service\ServiceInvoiceController;
use Illuminate\Support\Facades\Route;

// Home services - same paths and payloads as the Taksh API (the website page itself is /services).
Route::prefix('service')->name('service.')->group(function () {
    Route::get('services', [ServiceCatalogController::class, 'index'])->name('catalog');

    Route::middleware('auth')->group(function () {
        Route::post('book', [ServiceBookingController::class, 'book'])->middleware('throttle:20,1')->name('book');
        Route::post('book/verify', [ServiceBookingController::class, 'verify'])->middleware('throttle:20,1')->name('verify');
        Route::get('book', [ServiceBookingController::class, 'index'])->name('index');
        Route::get('book/{id}', [ServiceBookingController::class, 'show'])->where('id', '[A-Za-z0-9]+')->name('show');
        Route::post('book/{id}/cancel', [ServiceBookingController::class, 'cancel'])->where('id', '[A-Za-z0-9]+')->middleware('throttle:10,1')->name('cancel');
        Route::get('book/{id}/invoice', [ServiceInvoiceController::class, 'marketplace'])->where('id', '[A-Za-z0-9]+')->name('invoice');

        // courier booking
        Route::prefix('courier')->name('courier.')->group(function () {
            Route::get('delivery-partners', [CourierController::class, 'partners'])->name('partners');
            Route::post('quote', [CourierController::class, 'quote'])->middleware('throttle:30,1')->name('quote');
            Route::get('bookings', [CourierController::class, 'index'])->name('index');
            Route::post('bookings', [CourierController::class, 'store'])->middleware('throttle:10,1')->name('store');
            Route::get('bookings/{id}', [CourierController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('bookings/{id}/shiprocket', [CourierController::class, 'createShipment'])->whereNumber('id')->middleware('throttle:10,1')->name('shipment');
            Route::get('bookings/{id}/invoice', [ServiceInvoiceController::class, 'courier'])->whereNumber('id')->name('invoice');
            Route::post('bookings/{id}/cancel', [CourierController::class, 'cancel'])->whereNumber('id')->middleware('throttle:10,1')->name('cancel');
            Route::post('bookings/{id}/track', [CourierController::class, 'track'])->whereNumber('id')->middleware('throttle:12,1')->name('track');
            Route::post('payment/create', [CourierController::class, 'createPayment'])->middleware('throttle:20,1')->name('payment.create');
            Route::post('payment/verify', [CourierController::class, 'verifyPayment'])->middleware('throttle:20,1')->name('payment.verify');
        });
    });
});
