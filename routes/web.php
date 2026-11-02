<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/shop', [PageController::class, 'listing'])->name('listing');
Route::get('/product', [PageController::class, 'product'])->name('product');
Route::get('/cart', [PageController::class, 'cart'])->name('cart');
Route::get('/checkout', [PageController::class, 'checkout'])->name('checkout');
Route::get('/tracking', [PageController::class, 'tracking'])->name('tracking');
Route::get('/profile', [PageController::class, 'profile'])->name('profile');
Route::get('/notifications', [PageController::class, 'notifications'])->name('notifications');
Route::get('/services', [PageController::class, 'services'])->name('services');
Route::get('/wishlist', [PageController::class, 'wishlist'])->name('wishlist');
Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/login', [PageController::class, 'login'])->name('login');
Route::get('/wallet', [PageController::class, 'wallet'])->name('wallet');
Route::get('/help', [PageController::class, 'help'])->name('help');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/refund-policy', [PageController::class, 'refund'])->name('refund');
Route::get('/join/delivery-partner', [PageController::class, 'delivery'])->name('delivery');
Route::get('/join/vendor', [PageController::class, 'vendor'])->name('vendor');
Route::get('/development-request', [PageController::class, 'devrequest'])->name('devrequest');
Route::get('/return', [PageController::class, 'returns'])->name('returns');
Route::get('/payment/result', [PageController::class, 'payresult'])->name('payresult');
