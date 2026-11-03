<?php

use App\Http\Controllers\Ajax\Order\InvoiceController;
use App\Http\Controllers\Ajax\Order\OrderController;
use App\Http\Controllers\Ajax\Order\PaymentController;
use App\Http\Controllers\Ajax\Order\PaymentOptionController;
use App\Http\Controllers\Ajax\Order\ReturnController;
use Illuminate\Support\Facades\Route;

// Which ways to pay are open (public)
Route::get('payment-options', [PaymentOptionController::class, 'index'])->name('payment-options');

// Place an order and pay for it - same endpoints and payloads as the Taksh API.
Route::middleware('auth')->group(function () {
    Route::prefix('order')->name('order.')->group(function () {
        Route::post('place', [OrderController::class, 'store'])->middleware('throttle:20,1')->name('place');
        Route::get('orders', [OrderController::class, 'index'])->name('index');
        Route::get('orders/{id}', [OrderController::class, 'show'])->whereNumber('id')->name('show');
        Route::post('orders/{order_id}/cancel', [OrderController::class, 'cancel'])->whereNumber('order_id')->middleware('throttle:20,1')->name('cancel');
        Route::get('orders/{id}/delivery-location', [OrderController::class, 'deliveryLocation'])->whereNumber('id')->name('location');
    });

    // The customer's own invoice (a PDF rendered by taksh-admin)
    Route::get('orders/{order_number}/invoice', [InvoiceController::class, 'download'])->name('orders.invoice');

    // Returns: request one, send pictures, follow it
    Route::prefix('return/customer')->name('return.')->group(function () {
        Route::post('create', [ReturnController::class, 'create'])->middleware('throttle:20,1')->name('create');
        Route::post('upload-media', [ReturnController::class, 'uploadMedia'])->middleware('throttle:20,1')->name('media');
        Route::get('list', [ReturnController::class, 'index'])->name('list');
        Route::get('{id}', [ReturnController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('{id}/track', [ReturnController::class, 'track'])->whereNumber('id')->name('track');
    });

    Route::prefix('payments')->name('payments.')->group(function () {
        Route::post('create', [PaymentController::class, 'create'])->middleware('throttle:30,1')->name('create');
        Route::post('verify', [PaymentController::class, 'verify'])->middleware('throttle:30,1')->name('verify');
        Route::get('status/{orderId}', [PaymentController::class, 'status'])->whereNumber('orderId')->name('status');
    });
});
