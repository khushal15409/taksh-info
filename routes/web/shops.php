<?php

use App\Http\Controllers\Web\Shops\ShopPageController;
use Illuminate\Support\Facades\Route;

// Shops: every approved vendor, and one shop's own products page (the page a shared shop link opens).
Route::get('/shops', [ShopPageController::class, 'index'])->middleware('hidefilters:shops')->name('shops');
Route::post('/shops', \App\Http\Controllers\Web\FilterRedirectController::class)->middleware('hidefilters:shops')->name('shops.search');
Route::get('/shops/{id}', [ShopPageController::class, 'show'])->where('id', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->middleware(['urlkey:shop', 'hidefilters:shop'])->name('shop');
Route::post('/shops/{id}', \App\Http\Controllers\Web\FilterRedirectController::class)->where('id', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->middleware(['urlkey:shop', 'hidefilters:shop'])->name('shop.search');
