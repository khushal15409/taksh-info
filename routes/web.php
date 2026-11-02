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
