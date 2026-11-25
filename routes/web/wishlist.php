<?php

use App\Http\Controllers\Web\Wishlist\WishlistPageController;
use Illuminate\Support\Facades\Route;

Route::get('/wishlist', [WishlistPageController::class, 'index'])->name('wishlist');
Route::delete('/wishlist/{productId}', [WishlistPageController::class, 'destroy'])->middleware(['auth', 'urlkey:product,productId'])->where('productId', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->name('wishlist.forget');
