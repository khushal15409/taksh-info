<?php

use App\Http\Controllers\Web\Checkout\CheckoutPageController;
use App\Http\Controllers\Web\Checkout\OrderPayPageController;
use App\Http\Controllers\Web\Checkout\OrderPlacedPageController;
use App\Http\Controllers\Web\Checkout\PaymentResultPageController;
use Illuminate\Support\Facades\Route;

// Checkout and what follows it. The Razorpay calls themselves are the JSON endpoints in routes/order.php (/payments/create, /payments/verify).
Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutPageController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutPageController::class, 'place'])->middleware('throttle:20,1')->name('checkout.place');
    Route::get('/order/placed', [OrderPlacedPageController::class, 'show'])->middleware('urlkey:order,order')->name('order.placed');
    Route::get('/order/pay', [OrderPayPageController::class, 'show'])->middleware('urlkey:order,order_id')->name('order.pay');
});

Route::get('/payment/result', [PaymentResultPageController::class, 'show'])->middleware(['urlkey:order,order', 'hidefilters:payresult'])->name('payresult');
