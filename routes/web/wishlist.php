<?php

use App\Http\Controllers\Web\Wishlist\WishlistPageController;
use Illuminate\Support\Facades\Route;

Route::get('/wishlist', [WishlistPageController::class, 'index'])->name('wishlist');
Route::delete('/wishlist/{productId}', [WishlistPageController::class, 'destroy'])->middleware('auth')->whereNumber('productId')->name('wishlist.forget');
