<?php

use App\Http\Controllers\Ajax\Home\AppChargeController;
use App\Http\Controllers\Ajax\Home\BannerController;
use App\Http\Controllers\Ajax\Home\DashboardController;
use App\Http\Controllers\Ajax\Home\RecentController;
use App\Http\Controllers\Ajax\Home\SaleController;
use Illuminate\Support\Facades\Route;

// Home & discovery data - same paths and payloads as the Taksh API.
Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('app-charges', [AppChargeController::class, 'index'])->name('app-charges');

Route::prefix('ecommerce')->name('ecommerce.')->group(function () {
    Route::get('banners', [BannerController::class, 'index'])->name('banners');
    Route::get('sales', [SaleController::class, 'index'])->name('sales');
    Route::get('sales/{id}', [SaleController::class, 'show'])->whereNumber('id')->name('sales.show');
    Route::get('sales/{id}/products', [SaleController::class, 'products'])->whereNumber('id')->name('sales.products');
});

Route::middleware('auth')->get('product/recent-views', [RecentController::class, 'recentViews'])->name('recent-views');
