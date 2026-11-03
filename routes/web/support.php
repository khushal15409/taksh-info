<?php

use App\Http\Controllers\Web\Support\SupportPageController;
use Illuminate\Support\Facades\Route;

// Enquiry forms and the help page. (The JSON versions the mobile app uses are in routes/support.php.)
Route::get('/join/delivery-partner', [SupportPageController::class, 'form'])->defaults('key', 'delivery')->name('delivery');
Route::get('/join/vendor', [SupportPageController::class, 'form'])->defaults('key', 'vendor')->name('vendor');
Route::get('/development-request', [SupportPageController::class, 'form'])->defaults('key', 'devrequest')->name('devrequest');
Route::get('/help', [SupportPageController::class, 'help'])->name('help');

Route::post('/support/delivery-partner', [SupportPageController::class, 'storeDelivery'])->middleware('throttle:5,1')->name('support.delivery.store');
Route::post('/support/vendor', [SupportPageController::class, 'storeVendor'])->middleware('throttle:5,1')->name('support.vendor.store');
Route::post('/support/development', [SupportPageController::class, 'storeDevelopment'])->middleware('throttle:5,1')->name('support.development.store');
Route::post('/support/callback', [SupportPageController::class, 'callback'])->middleware(['auth', 'throttle:5,1'])->name('support.callback.store');
