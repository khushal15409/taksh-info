<?php

use App\Http\Controllers\Ajax\Cart\CartController;
use Illuminate\Support\Facades\Route;

// Cart - same paths and payloads as the Taksh API (a guest's cart is tied to their session instead of a token in the request).
Route::prefix('cart')->name('cart.')->group(function () {
    Route::post('add', [CartController::class, 'add'])->name('add');
    Route::get('cart', [CartController::class, 'index'])->name('index');
    Route::post('update', [CartController::class, 'update'])->name('update');
    Route::post('item/{id}', [CartController::class, 'destroy'])->whereNumber('id')->name('destroy');
    Route::delete('clear', [CartController::class, 'clear'])->name('clear');
    Route::post('coupon/apply', [CartController::class, 'applyCoupon'])->name('coupon.apply');
    Route::post('coupon/remove', [CartController::class, 'removeCoupon'])->name('coupon.remove');
});
