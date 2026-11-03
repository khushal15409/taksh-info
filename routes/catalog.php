<?php

use App\Http\Controllers\Ajax\Catalog\CategoryController;
use App\Http\Controllers\Ajax\Catalog\DeliveryCheckController;
use App\Http\Controllers\Ajax\Catalog\ProductController;
use App\Http\Controllers\Ajax\Catalog\ReviewController;
use App\Http\Controllers\Ajax\Catalog\SearchController;
use Illuminate\Support\Facades\Route;

// Catalogue data - same paths and payloads as the Taksh API.
Route::prefix('ecommerce')->name('ecommerce.')->group(function () {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories');
    Route::get('categories/parents', [CategoryController::class, 'parents'])->name('categories.parents');
    Route::get('categories/subcategories', [CategoryController::class, 'subCategories'])->name('categories.sub');

    Route::get('products', [ProductController::class, 'index'])->name('products');
    Route::get('products/{id}', [ProductController::class, 'show'])->whereNumber('id')->name('products.show');
    Route::get('products/{id}/similar', [ProductController::class, 'similar'])->whereNumber('id')->name('products.similar');
    Route::post('products/{id}/check-delivery', [DeliveryCheckController::class, 'check'])->whereNumber('id')->name('products.check-delivery');
});

Route::get('products/search', [SearchController::class, 'search'])->name('search');
Route::get('product/attributes', [ProductController::class, 'attributes'])->name('attributes');

// Signed-in customers
Route::middleware('auth')->group(function () {
    Route::get('product/recent-searches', [SearchController::class, 'recent'])->name('recent-searches');
    Route::get('reviews/pending', [ReviewController::class, 'pending'])->name('reviews.pending');
    Route::get('product/{id}/review', [ReviewController::class, 'show'])->whereNumber('id')->name('reviews.show');
    Route::post('product/{id}/review', [ReviewController::class, 'store'])->whereNumber('id')->middleware('throttle:30,1')->name('reviews.store');
});
