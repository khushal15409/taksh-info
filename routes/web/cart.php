<?php

use App\Http\Controllers\Web\Cart\CartPageController;
use Illuminate\Support\Facades\Route;

// The cart page and its actions. (The /cart/add, /cart/update... JSON endpoints used by the "Add" buttons live in routes/cart.php.)
Route::get('/cart', [CartPageController::class, 'show'])->name('cart');
Route::post('/cart/lines/{id}/quantity', [CartPageController::class, 'updateLine'])->where('id', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->middleware('urlkey:cartline')->name('cart.line.update');
Route::delete('/cart/lines/{id}', [CartPageController::class, 'removeLine'])->where('id', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->middleware('urlkey:cartline')->name('cart.line.remove');
Route::delete('/cart', [CartPageController::class, 'empty'])->name('cart.empty');
Route::post('/cart/coupon', [CartPageController::class, 'applyCoupon'])->middleware('throttle:20,1')->name('cart.coupon.save');
Route::delete('/cart/coupon', [CartPageController::class, 'removeCoupon'])->name('cart.coupon.delete');
