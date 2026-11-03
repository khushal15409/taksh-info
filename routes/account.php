<?php

use App\Http\Controllers\Ajax\Account\AddressController;
use App\Http\Controllers\Ajax\Account\LocationController;
use App\Http\Controllers\Ajax\Account\WishlistController;
use Illuminate\Support\Facades\Route;

// Signed-in customer's wishlist and addresses - same payloads as the Taksh API.
Route::middleware('auth')->group(function () {
    Route::prefix('wishlist')->name('wishlist.')->group(function () {
        Route::get('items', [WishlistController::class, 'index'])->name('index');
        Route::post('add', [WishlistController::class, 'add'])->name('add');
        Route::post('remove/{productId}', [WishlistController::class, 'remove'])->whereNumber('productId')->name('remove');
    });

    Route::prefix('address')->name('address.')->group(function () {
        Route::post('add', [AddressController::class, 'store'])->name('add');
        Route::get('addresses', [AddressController::class, 'index'])->name('index');
        Route::get('user/addresses', [AddressController::class, 'index'])->name('user.index');
        Route::post('user/addresses', [AddressController::class, 'store'])->name('user.store');
        Route::post('user/addresses/set-default', [AddressController::class, 'setDefault'])->name('default');
        Route::post('user/addresses/delete', [AddressController::class, 'destroy'])->name('delete');
        Route::put('{id}', [AddressController::class, 'update'])->whereNumber('id')->name('update');
    });
});

// Map place search / reverse lookup for the address form (public, throttled; answers are cached)
Route::prefix('location')->middleware('throttle:30,1')->name('location.')->group(function () {
    Route::get('search', [LocationController::class, 'search'])->name('search');
    Route::get('geocode', [LocationController::class, 'geocode'])->name('geocode');
    Route::get('reverse-geocode', [LocationController::class, 'reverse'])->name('reverse');
});
