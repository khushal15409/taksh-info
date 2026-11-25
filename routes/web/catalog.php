<?php

use App\Http\Controllers\Web\Catalog\HomePageController;
use App\Http\Controllers\Web\Catalog\ListingPageController;
use App\Http\Controllers\Web\Catalog\ProductPageController;
use App\Http\Controllers\Web\Catalog\SalePageController;
use Illuminate\Support\Facades\Route;

// Catalogue pages. The shop also answers search (?q=); a product is /product/{key}.
Route::get('/', [HomePageController::class, 'show'])->name('home');
Route::get('/shop', [ListingPageController::class, 'show'])->middleware('hidefilters:listing')->name('listing');
Route::post('/shop', \App\Http\Controllers\Web\FilterRedirectController::class)->middleware('hidefilters:listing')->name('listing.search');   // the search / filter forms POST here; the answer is a redirect to /shop?f=<token>
Route::get('/product/{id}', [ProductPageController::class, 'show'])->where('id', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->middleware('urlkey:product')->name('product');
Route::get('/product', [ProductPageController::class, 'legacy'])->name('product.legacy');   // the old /product?id= link and the scripts' links: sent on to /product/{key}
Route::get('/sales', [SalePageController::class, 'index'])->name('sales');
Route::get('/sales/{id}', [SalePageController::class, 'show'])->where('id', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->middleware('urlkey:sale')->name('sales.detail');
