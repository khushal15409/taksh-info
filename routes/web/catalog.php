<?php

use App\Http\Controllers\Web\Catalog\HomePageController;
use App\Http\Controllers\Web\Catalog\ListingPageController;
use App\Http\Controllers\Web\Catalog\ProductPageController;
use App\Http\Controllers\Web\Catalog\SalePageController;
use Illuminate\Support\Facades\Route;

// Catalogue pages. The shop also answers search (?q=); a product is /product?id=.
Route::get('/', [HomePageController::class, 'show'])->name('home');
Route::get('/shop', [ListingPageController::class, 'show'])->name('listing');
Route::get('/product', [ProductPageController::class, 'show'])->name('product');
Route::get('/sales', [SalePageController::class, 'index'])->name('sales');
Route::get('/sales/{id}', [SalePageController::class, 'show'])->whereNumber('id')->name('sales.detail');
